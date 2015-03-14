<?php 
/**
 * The main component for mocking php class
 */
namespace PhlexMock; 

class PhlexMock 
{
    private $classSearchPaths;
    private $classExtension;
    private $classBuffer;
    private $parser;
    private $serializer;

    public function __construct()
    {
        $this->classSearchPaths = [];
        $this->classExtension = [];
        $this->classBuffer = [];
        $this->version = 0;
    }

    public function addClassSearchPath($classSearchPath)
    {
        $this->classSearchPaths[] = $classSearchPath;
    }

    public function setClassExtension($classExtension)
    {
        $this->classExtension = $classExtension; 
    }

    /**
     * define a custom method
     */
    public function method($name, $callback)
    {
        
    }

    public function start()
    {
        $this->version ++;
        spl_autoload_register(array($this, 'loadClassIntoBuffer'), false, true);    
        //initiate parser and serializer after autoloader is registered
        $this->parser = new \PhpParser\Parser(new \PhpParser\Lexer());
        $this->serializer = new \PhpParser\Serializer\XML();
    }

    private function loadClassIntoBuffer($class)
    {
        foreach($this->classSearchPaths as $classSearchPath) {
            $nameStr = '';
            foreach($this->classExtension as $index => $extension) {
                if ($index == 0) {
                    $nameStr .= "-name '$class.".$extension."' -o ";
                } else {
                    $nameStr .= "-name '$class.".$extension."'";
                }
            }
            $cmd = 'find '.$classSearchPath.' -type f '.$nameStr;
            $classFile = trim(shell_exec($cmd));
            if (strlen($classFile) > 0) {
                if (strpos($classFile,"/vendor/") !== FALSE) { #this is third party lilbrary, we need to load them up
                    $classCode = file_get_contents($classFile);
                    eval($classCode);
                } else { //this is custom class, perform static code analysis  
                    try {
                        $classCode = file_get_contents($classFile);
                        $stmts = $this->parser->parse($classCode);
                        $codeASTXML = $this->serializer->serialize($stmts); 
                        $codeLines = explode("\n", $classCode);
                        $codeASTXMLLines = explode("\n", $codeASTXML);
                        $classMap = $this->getClassMap($codeLines, $codeASTXMLLines);
                        print_r($classMap);exit();
                    } catch(\PhpParser\Error $e) {

                    }
                }
                $this->classBuffer[] = $classFile;
            }
        }    
    }

    private function getClassMap($codeLines, $codeASTXMLLines)
    {
        //get all classes info, with namespace
        $classInfos = array(); 

        $classMap = array();

        $namespace = "";
        $className = "";

        foreach($codeASTXMLLines as $index => $line)
        {
            if (strpos($line,"<node:Stmt_Namespace>") > 0) {
                $startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 2]);
                $namespace = str_replace(array("namespace ",";"),"",$codeLines[$startLine - 1]);
            } else if (strpos($line,"<node:Stmt_Class>") > 0) {
                $classInfo = new \stdClass();
                $classInfo->startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 2]);
                //is it an abstract class?
                $classInfo->isAbstract = false;
                if (strpos(trim($codeLines[$classInfo->startLine-1]), "abstract ") === 0) {
                    $classInfo->isAbstract = true;
                }
                $classInfo->endLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 5]);
                $classInfo->namespace = $namespace;
                $classInfo->className = "\\".$namespace."\\".trim(str_replace(array("<scalar:string>","</scalar:string>"),"",$codeASTXMLLines[$index + 11])); 
                $classInfo->methodInfos = array();
                $classInfo->properties = array();
                $classInfo->staticProperties = array();
                $className = $classInfo->className;

                $classInfos[] = $classInfo;   

                $classMap[$classInfo->className] = $classInfo;
            } else if (strpos($line, "<node:Stmt_Property>") > 0) {
                $propertyStartLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 2]);
                $propertyCode = $codeLines[$propertyStartLine - 1];
                $propertyInfo = new \stdClass();

                $propertyStartPos = strpos($propertyCode, "$");
                $propertyEndPos = strpos($propertyCode, ";");

                $propertyComps = explode("=",substr($propertyCode, $propertyStartPos + 1, $propertyEndPos - $propertyStartPos - 1));

                $propertyInfo->name = trim($propertyComps[0]);
                $propertyInfo->value = null;
                //some property might not have a value;
                if (count($propertyComps) > 1) {
                    $propertyInfo->value = str_replace('"',"'", trim($propertyComps[1]));
                }
                $propertyInfo->code = $propertyCode; 

                if(strpos($propertyCode, "static ") !== FALSE) {
                    //this is a static property 
                    $classMap[$className]->staticProperties[] = $propertyInfo;
                } else {
                    //this is a dynamic property   
                    $classMap[$className]->properties[] = $propertyInfo;
                }
            } else if (strpos($line,"<node:Stmt_ClassMethod>") > 0) {
                $classMethodInfo = new \stdClass();
                $classMethodInfo->startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 2]);
                $classMethodInfo->endLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 5]);
                $startLineContent = $codeLines[$classMethodInfo->startLine - 1];
                $classMethodInfo->name = trim(explode("function ",$startLineContent)[1]);
                $classMethodInfo->pureName = explode(" ", str_replace("(", " ", $classMethodInfo->name))[0];
                //now figure out where it is public, protected or private 

                //find out all methods belongs to this class
                foreach(array("public","protected","private") as $visibility) {
                    if (strpos($startLineContent,"$visibility ") !== FALSE) {
                        $classMethodInfo->visibility = $visibility;
                    }
                }

                if (!isset($classMethodInfo->visibility)) {
                    $classMethodInfo->visibility = "protected";
                }

                if (strpos($startLineContent, "static ") !== FALSE) {
                    $classMethodInfo->isStatic = true;
                } else {
                    $classMethodInfo->isStatic = false;
                }

                $classMap[$className]->methodInfos[$classMethodInfo->pureName] = $classMethodInfo;
            }
        }
        
        //now figure out the parent classes for each class
        foreach($classInfos as $index => $classInfo) {
            $line = trim($codeLines[$classInfo->startLine - 1]);
            if (strpos($line, " extends ") !== FALSE) {
                $lineComps = explode(" extends ", $line);
                $classMap[$classInfo->className]->parentClass = "\\".$classInfo->namespace."\\".trim(explode(" ",$lineComps[1])[0]);
            }
        }

        return $classMap;

    }
}
