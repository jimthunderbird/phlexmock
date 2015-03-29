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

    private static $specialMethods = [
        '__construct' => 1,
        '__destruct' => 1,
        '__call' => 1,
        '__callstatic' => 1,
        '__get' => 1,
        '__set' => 1   
    ];

    private static $closureContainerScriptLines = [];

    private static $classMethodHash = []; //store the current class method hash 

    private static $originalClassMethodHash = []; //store the original class method hash

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

    //reset to original class implementation
    public function reset()
    {
        self::$classMethodHash = [];
        foreach(self::$originalClassMethodHash as $className => $methodCodes) {
            foreach($methodCodes as $methodName => $code) {
                self::$classMethodHash[$className][$methodName] = $code;
            }
        }
    }

    public static function setClassMethodHash($classMethodHash)
    {
        self::$classMethodHash = $classMethodHash;
    }

    public static function getClassMethodHash()
    {
        return self::$classMethodHash;
    }

    public static function updateClassMethodHash($className, $methodName, $closure)
    {
        $methodName= strtolower($methodName);
        if (isset(self::$specialMethods[$methodName]) && self::$specialMethods[$methodName] == 1) { 
            $methodName= "phlexmock_".$methodName;
        }

        $closureRF = new \ReflectionFunction($closure);
        $paramStr = "()";
        $params = [];
        $closureParams = $closureRF->getParameters();

        if (count($closureParams) > 0) {
            foreach($closureParams as $closureParam) {
                $params[] = '$'.$closureParam->getName();
            }
            $paramStr = "(".implode(",",$params).")";
        }

        $sl = $closureRF->getStartLine();
        $el = $closureRF->getEndLine();
        $closureContainerScript = $closureRF->getFileName();
        if (!isset(self::$closureContainerScriptLines[$closureContainerScript])) {
            self::$closureContainerScriptLines[$closureContainerScript] = explode("\n",file_get_contents($closureRF->getFileName()));
        }
        $lines = self::$closureContainerScriptLines[$closureContainerScript];
        $code = '$func = function'.$paramStr.' { '.implode("\n",array_slice($lines, $sl, $el - $sl - 1)).' };';
        self::$classMethodHash[$className][$methodName] = $code;
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
            //see if the constructor and destructor exists in the class  
            $constructorExists = false;
            $destructorExists = false;

            foreach($classInfo->methodInfos as $name => $methodInfo) {

                $methodName = strtolower($name); #use lowercase method name so that we can have case insensitive method names
                //now need to remove all existing method code 

                for($l = $methodInfo->startLine; $l <= $methodInfo->endLine; $l++) {
                    $codeLines[$l - 1] = "";
                }

                if ($methodName == "__construct" || $methodName == "__destruct" || $methodName == "__get" || $methodName == "__set") {  
                    if ($name == "__construct") {
                        $constructorExists = true;
                    }
                    if ($name == "__destruct") {
                        $destructorExists = true;
                    }
                    $methodName = "phlexmock_".$methodName;

                    $codeLines[$l - 2] = "public function ".$methodInfo->name."{
                    \$args = func_get_args();
                    return call_user_func_array(array(\$this,'$methodName'), \$args);
                }";
                }

                if ($methodName == "__call" || $methodName == "__callstatic") {
                    $methodName = "phlexmock_".$methodName;
                }

                //we simply store the closure code to the class method hash and evaluate later 
                $code = "\$func=".str_replace($name, 'function',$methodInfo->name).$methodInfo->code.';';
                self::$classMethodHash[$className][$methodName] = $code;

                //also backup in the originalClassMethodHash 
                if (!isset(self::$originalClassMethodHash[$className][$methodName])) {
                   self::$originalClassMethodHash[$className][$methodName] = $code;
                }
            }

            if (!$constructorExists) { //if the constructor does not exist, fake one
                $codeLines[$classInfo->startLine + 1] = "\n\npublic function __construct() {
                    call_user_func_array(array(\$this,'phlexmock___construct'),func_get_args());
            }\n\n".$codeLines[$classInfo->startLine + 1];
            }

            if (!$destructorExists) { //if the destructor does not exist, fake one
                $codeLines[$classInfo->startLine + 1] = "\n\npublic function __destruct() {
                    call_user_func_array(array(\$this,'phlexmock___destruct'),array());
            }\n\n".$codeLines[$classInfo->startLine + 1];
            }


            $defineMethodHashCode = '';

            //add method to define method, we will store the actual closure code in string to the hash so that we can eval later on
            $defineMethodHashCode .= <<<DMH
public static function phlexmockMethod(\$name, \$closure) {
    \PhlexMock\PhlexMock::updateClassMethodHash('$className', \$name, \$closure);
}
DMH;

            $magicMethodCode = "";

            //add the magic method __call 
            $magicMethodCode .= <<<CODE
\n\npublic function __call(\$name, \$args){ 
    \$classMethodHash = \PhlexMock\PhlexMock::getClassMethodHash();
    \$lcName = strtolower(\$name);
    if (isset(\$classMethodHash['$className'][\$lcName])){
        eval(\$classMethodHash['$className'][\$lcName]);
        return call_user_func_array(\$func, \$args); 
    } else if (isset(\$classMethodHash['$className']['phlexmock___call'])) {
        eval(\$classMethodHash['$className']['phlexmock___call']);
        return \$func(\$name, \$args);
    } else {
        if (get_parent_class() !== FALSE) {
            return parent::__call(\$name, \$args);
        }
    }
}
CODE;

            //add the magic method __callStatic 
            $magicMethodCode .= <<<CODE
\n\npublic static function __callStatic(\$name, \$args){ 
    \$lcName = strtolower(\$name);
    \$classMethodHash = \PhlexMock\PhlexMock::getClassMethodHash();
    if (isset(\$classMethodHash['$className'][\$lcName])){
        eval(\$classMethodHash['$className'][\$lcName]);
        return call_user_func_array(\$func, \$args); 
    } else if (isset(\$classMethodHash['$className']['phlexmock___callstatic'])) {
        eval(\$classMethodHash['$className']['phlexmock___callstatic']);
        return \$func(\$name, \$args);
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
                $className = $classInfo->className;

                $classInfos[] = $classInfo;   

                $classMap[$classInfo->className] = $classInfo;
                //reset namespace to empty 
                $namespace = "";
            } else if (strpos($line,"<node:Stmt_ClassMethod>") > 0) {
                $classMethodInfo = new \stdClass();
                $classMethodInfo->startLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 2]);
                $classMethodInfo->endLine = (int)str_replace(array("<scalar:int>","</scalar:int>"),"",$codeASTXMLLines[$index + 5]);
                $startLineContent = $codeLines[$classMethodInfo->startLine - 1];
                $classMethodInfo->name = trim(explode("function ",$startLineContent)[1]);
                $classMethodInfo->pureName = explode(" ", str_replace("(", " ", $classMethodInfo->name))[0];

                $classMethodInfo->code = implode("\n",array_slice($codeLines, $classMethodInfo->startLine, $classMethodInfo->endLine - $classMethodInfo->startLine));

                $classMap[$className]->methodInfos[$classMethodInfo->pureName] = $classMethodInfo;
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
