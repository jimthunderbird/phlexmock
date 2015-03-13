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
    private $version;
    private $parser;
    private $serializer;

    public function __construct()
    {
        $this->classSearchPaths = [];
        $this->classExtension = 'php';
        $this->classBuffer = [];
        $this->version = 0;
        $this->parser = new PhpParser\Parser(new PhpParser\Lexer);
        $this->serializer = new PhpParser\Serializer\XML();
    }

    public function addClassSearchPath($classSearchPath)
    {
        $this->classSearchPaths[] = $classSearchPath;
    }

    public function setClassExtension($classExtension)
    {
        $this->classExtension = $classExtension; 
    }

    public function start()
    {
        $this->version ++;
        spl_autoload(array($this, 'loadClassIntoBuffer'), false, true);    
    }

    private function loadClassIntoBuffer($class)
    {
        foreach($this->classSearchPaths as $classSearchPath) {
            $classFile = trim(shell_exec('find '.$classSearchPath.' -type f -name '.$class.'.'.$this->classExtension));
            if (strlen($classFile) > 0) {
                $classCode = file_get_contents($classFile);
                try {
                    $stmts = $this->parser->parse($classCode);
                    print $this->serializer->serialize($stmts);
                    $this->classBuffer[] = $classFile;
                } catch (PhpParser\Error $e) {
                    
                }
            }
            exit();
        }    
    }
}
