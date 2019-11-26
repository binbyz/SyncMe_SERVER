<?php
/**
 * autoload
 */
include_once 'autoload.php';

/**
 * constant
 */
include_once 'constant.php';

/**
 * handleDB
 * 
 * 모든 DB 인스턴트는 해당 메서드를 통해 처리한다
 */
if (! function_exists('handleDB')) {
    function handleDB(string $dbType = 'mongo') {
        return \onair\lib\InstanceFacade::getDBInstance($dbType);
    }
}

/**
 * handleRequest
 * 
 * 모든 $_POST 또는 $_GET의 변수값을 해당 메서드를 통해 처리한다
 */
if (! function_exists('handleRequest')) {
    function handleRequest(string $key) {
        return \onair\lib\InstanceFacade::getSecurityRequest('POST', $key);
    }
}

/**
 * toObject
 */
if (! function_exists('toObject')) {
    function toObject(...$args) {
        $count = count($args);

        if ($count === 0) {
            return new \stdClass;
        } 
        else if ($count === 1) {
            if ($args[0] instanceof \Array) {
                return (object) $args[0];
            } 
            else {
                return $args[0];
            }
        } 
        else {
            $i = 0;
            $bulk = [];

            foreach ($args as $v) {
                if ($v instanceof \onair\lib\InjectionSecurity) {
                    $bulk[ $v->getKey() ] = $v->get()
                } 
                else {
                    $bulk[ $i ] = $v;
                }

                $i += 1;
            }
        }
    }
}

/**
 * Easy 디버그 도구
 */
if (! function_exists('dd')) {
    function dd($data) {
        highlight_string("<?php\n\$data =\n" . var_export($data, true) . ";\n?>");
    }
}