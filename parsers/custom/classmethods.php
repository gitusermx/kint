<?php

class Kint_Parsers_ClassMethods extends kintParser
{
    protected function _parse(&$variable)
    {
        if(!is_object($variable)) {
            return false;
        }

        $reflection = new \ReflectionClass($variable);

        $public = $private = $protected = array();

        // Class methods
        foreach($reflection->getMethods() as $method) {
            $params = array();

            // Access type
            $access = implode(' ', \Reflection::getModifierNames($method->getModifiers()));

            // Method parameters
            foreach($method->getParameters() as $param) {
                $paramString = '';

                if($param->isArray()) {
                    $paramString .= 'array ';
                } elseif($param->getClass()) {
                    $paramString .= $param->getClass()->name . ' ';
                }

                $paramString .= ($param->isPassedByReference() ? '&' : '') . '$' . $param->getName();

                if($param->isDefaultValueAvailable()) {
                    if(is_array($param->getDefaultValue())) {
                        $arrayValues = array();
                        foreach($param->getDefaultValue() as $key => $value) {
                            $arrayValues[] = $key . ' => ' . $value;
                        }

                        $defaultValue = 'array(' . implode(', ', $arrayValues) . ')';
                    } elseif($param->getDefaultValue() === null){
                        $defaultValue = 'NULL';
                    } elseif($param->getDefaultValue() === false){
                        $defaultValue = 'false';
                    } elseif($param->getDefaultValue() === true){
                        $defaultValue = 'true';
                    } elseif($param->getDefaultValue() === ''){
                        $defaultValue = '""';
                    } else {
                        $defaultValue = $param->getDefaultValue();
                    }

                    $paramString .= ' = ' . $defaultValue;
                }

                $params[] = $paramString;
            }

            $return = false;

            // Simple DocBlock parser, look for @return
            if(($docBlock = $method->getDocComment())) {
                $matches = array();
                if(preg_match_all('/@(\w+)\s+(.*)\r?\n/m', $docBlock, $matches)) {
                    $lines = array_combine($matches[1], $matches[2]);
                    if(isset($lines['return'])) {
                        $return = $lines['return'];
                    }
                }
            }

            $output = new \kintVariableData();
            $output->name = ($method->returnsReference() ? '&' : '') . $method->getName() . '(' . implode(', ', $params) . ')';
            $output->access = $access;

            if(is_string($docBlock)) {
                $lines = array();
                foreach(explode("\n", $docBlock) as $line) {
                    $line = trim($line);

                    if(in_array($line, array('/**', '/*', '*/'))) {
                        continue;
                    }elseif(strpos($line, '*') === 0) {
                        $line = substr($line, 1);
                    }

                    $lines[] = trim($line);
                }

                $output->extendedValue = implode("\n", $lines);
            }

            if($return) {
                $output->operator = '->';
                $output->type = $return;
            }

            $sortName = $access . $method->getName();

            if($method->isPrivate()) {
                $private[$sortName] = $output;
            } elseif($method->isProtected()) {
                $protected[$sortName] = $output;
            } else {
                $public[$sortName] = $output;
            }
        }

        if(!$private && !$protected && !$public) {
            return false;
        }

        ksort($public);
        ksort($protected);
        ksort($private);

        $extendedValue = $public + $protected + $private;

        $this->value = $extendedValue;
        $this->type = 'methods';
        $this->size = count($extendedValue);
    }
}