<?php 
/**
 * The main component for mocking php class
 */
namespace PhlexMock; 

class PhlexMock 
{
    private $classSearchPaths;
    private $classExtensions;
    private $excludePatterns;

    private $classFileMap;
    private $fileIndex;

    private $parser;
    private $serializer;

    public function __construct()
    {
        $this->classSearchPaths = [];
        $this->classExtensions = ['php','class.php']; //default look for .php and class.php
        $this->classFileMap = [];
        $this->fileIndex = [];
    }

    public function setClassSearchPaths($classSearchPaths)
    {
        $this->classSearchPaths = $classSearchPaths;
    }

    public function setClassExtensions($classExtension)
    {
        $this->classExtensions = $classExtension; 
    }

    public function setClassFileMap($classFileMap)
    {
        $this->classFileMap = $classFileMap;
    }

    public function start()
    {
        $this->generateFileIndex();
        //set up the autoloader
        spl_autoload_register(array($this, 'loadClassIntoBuffer'), false, true);    
        //initiate parser and serializer after autoloader is registered 
        $this->parser = new \PhpParser\Parser(new \PhpParser\Lexer());
        $this->serializer = new \PhpParser\Serializer\XML();
    }

    private function loadClassIntoBuffer($class)
    {
        $class = str_replace("\\","/",$class);

        $classFile = null;

        //first check if the class is matched in the classFileMap 
        if (count($this->classFileMap) > 0 && isset($this->classFileMap[$class])) {
            $classFile = $this->classFileMap[$class];
            $this->parseAndEvaluateClassFile($classFile);
            return;
        }

        //continue searching for the class        
        foreach($this->fileIndex as $file) {
            $classFound = false;
            foreach($this->classExtensions as $extension) {
                $classFound = $classFound || (strpos($file,"$class.$extension") !== FALSE);
            }
            if ($classFound) { 
                if (strpos($file,"/vendor/") !== FALSE) { #this is third party lilbrary, load it up 
                    return;
                } else if (strpos($class, 'PhlexMock') !== FALSE) { #this is phlexmock related classes, load it up 
                    return;
                } else {
                    $classFile = $file;
                    $this->parseAndEvaluateClassFile($classFile);
                    break;
                }
            } 
        }

    }

    private function parseAndEvaluateClassFile($classFile)
    {
        try {
            $classCode = $this->getFinalClassCode($classFile);
            eval($classCode);
        } catch(\PhpParser\Error $e) {
            echo "PHP Parser Error: ".$e->getMessage();
        }
    }

    private function getFinalClassCode($classFile)
    {
        $classCode = file_get_contents($classFile);
        $stmts = $this->parser->parse($classCode);
        $codeASTXML = $this->serializer->serialize($stmts); 
        $codeLines = explode("\n", $classCode);
        $codeASTXMLLines = explode("\n", $codeASTXML);
        $classMap = $this->getClassMap($codeLines, $codeASTXMLLines);

        //now reopen all methods ... 

        foreach($classMap as $className => $classInfo) {
            //now add all methods into global hash
            foreach($classInfo->methodInfos as $name => $methodInfo) {

                $methodName = strtolower($name); #use lowercase method name so that we can have case insensitive method names
                //now need to remove all existing method code 

                for($l = $methodInfo->startLine; $l <= $methodInfo->endLine; $l++) {
                    $codeLines[$l - 1] = "";
                }

                if ($name == "__construct" || $name == "__destruct") { //this is the constructor or destructor 
                    $methodName = "phlexmock_".$methodName;
                    for($l = $methodInfo->startLine; $l <= $methodInfo->endLine; $l++) {
                        $codeLines[$l - 1] = "";
                    }
                    //now add the fake constructor and destructor
                    $codeLines[$l - 2] = "public function ".$methodInfo->name."{
                    \$args = func_get_args();
                    call_user_func_array(array(\$this,'$methodName'), \$args);
                }";
                }

                //we simply store the closure clode to global and will evaluate later
                $GLOBALS['phlexmock_method_hash'][$className][$methodName] = "\$func=".str_replace($name, 'function',$methodInfo->name).$methodInfo->code.';';
            }

            $defineMethodHashCode = '';

            //add method to define method, we will store the actual closure code in string to the hash so that we can eval later on
            $defineMethodHashCode .= <<<DMH
public static function phlexmockMethod(\$name, \$closure) {
    \$name = strtolower(\$name);
    if (\$name == "__construct" || \$name == "__destruct") { //special treatment for constructor and destructor!
        \$name = "phlexmock_".\$name;
    }
    \$closureRF = new \ReflectionFunction(\$closure);
    \$paramStr = "()";
    \$params = [];
    \$closureParams = \$closureRF->getParameters();

    if (count(\$closureParams) > 0) {
        foreach(\$closureParams as \$closureParam) {
            \$params[] = '$'.\$closureParam->getName();
        }
        \$paramStr = "(".implode(",",\$params).")";
    }
    \$sl = \$closureRF->getStartLine();
    \$el = \$closureRF->getEndLine();
    \$lines = explode("\\n",file_get_contents(\$closureRF->getFileName()));
    \$code = '\$func = function'.\$paramStr.' { '.implode("\\n",array_slice(\$lines, \$sl, \$el - \$sl - 1)).' };';
    \$GLOBALS['phlexmock_method_hash']['$className'][\$name] = \$code;
}
DMH;

$magicMethodCode = "";

//add the magic method __call 
$magicMethodCode .= <<<CODE
public function __call(\$name, \$args){ 
    \$name = strtolower(\$name);
    if (isset(\$GLOBALS['phlexmock_method_hash']['$className'][\$name])){
        eval(\$GLOBALS['phlexmock_method_hash']['$className'][\$name]);
        return call_user_func_array(\$func, \$args); 
    } else {
        if (get_parent_class() !== FALSE) {
            return parent::__call(\$name, \$args);
        }
    }
}
CODE;

//add the magic method __callStatic 
$magicMethodCode .= <<<CODE
public static function __callStatic(\$name, \$args){ 
    \$name = strtolower(\$name);
    if (isset(\$GLOBALS['phlexmock_method_hash']['$className'][\$name])){
        eval(\$GLOBALS['phlexmock_method_hash']['$className'][\$name]);
        return call_user_func_array(\$func, \$args); 
    } else {
        if (get_parent_class() !== FALSE) {
            return parent::__callStatic(\$name, \$args);
        }
    }
}
CODE;

$codeLines[$classInfo->startLine + 1] = $defineMethodHashCode."\n\n".$magicMethodCode.$codeLines[$classInfo->startLine + 1];

}

$classCode = implode("\n",$codeLines);
//now eval the class code 
$classCode = str_replace('<?php','',$classCode);
$classCode = $this->removeBlankLines($classCode);
return $classCode;
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
                if (strlen($namespace) > 0) {
                    $namespace = "\\".$namespace."\\";
                } else {
                    $namespace = "\\";
                }
                $classInfo->namespace = $namespace;
                $classInfo->className = $namespace.trim(str_replace(array("<scalar:string>","</scalar:string>"),"",$codeASTXMLLines[$index + 11])); # for Php Parser 1.0.x it is $index + 11 
                $classInfo->pureName = array_pop(explode("\\",$classInfo->className));
                $classInfo->methodInfos = array();
                $classInfo->properties = array();
                $classInfo->staticProperties = array();
                $className = $classInfo->className;

                $classInfos[] = $classInfo;   

                $classMap[$classInfo->className] = $classInfo;
                //reset namespace to empty 
                $namespace = "";
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

                $classMethodInfo->code = implode("\n",array_slice($codeLines, $classMethodInfo->startLine, $classMethodInfo->endLine - $classMethodInfo->startLine));

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
                $namespace = "\\";
                if ($classInfo->namespace !== "\\") {
                    $namespace = "\\".$classInfo->namespace."\\";
                }
                $classMap[$classInfo->className]->parentClass = $namespace.trim(explode(" ",$lineComps[1])[0]);
            }
        }

        return $classMap;

    }

    private function generateFileIndex()
    {
        $nameStr = '';
        foreach($this->classExtensions as $index => $extension) {
            if ($index == 0) {
                $nameStr .= "-name '*.".$extension."' -o ";
            } else {
                $nameStr .= "-name '*.".$extension."'";
            }
        }

        $files = [];
        foreach($this->classSearchPaths as $classSearchPath) {
            $cmd = 'find '.$classSearchPath.' -type f '.$nameStr;
            $files = array_merge($files, explode("\n",trim(shell_exec($cmd))));
        }

        $this->fileIndex = $files;
    }

    private function removeBlankLines($content)
    {
        //now remove all blank lines, credit: http://stackoverflow.com/questions/709669/how-do-i-remove-blank-lines-from-text-in-php   
        $content = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $content);
        return $content;
    }

}
