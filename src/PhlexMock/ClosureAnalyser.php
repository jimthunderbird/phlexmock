<?php
class ClosureAnalyser
{
    private $closure;
    private $closureRF;

    public function setClosure($closure)
    {
        $this->closure = $closure;
        $this->closureRF = new ReflectionFunction($closure);
    }

    public function getCode()
    {
        $sl = $this->closureRF->getStartLine();
        $el = $this->closureRF->getEndLine();
        $lines = explode("\n",file_get_contents(__FILE__));
        $code = implode("\n",array_slice($lines, $sl, $el - $sl - 1));
        return $code;
    }

    public function getParams()
    {
        $params = [];

        $closureParams = $this->closureRF->getParameters();

        if (count($closureParams) > 0) {
            foreach($closureParams as $closureParam) {
                $params[] = '$'.$closureParam->getName();
            }
        }

        return $params;
    }
}
