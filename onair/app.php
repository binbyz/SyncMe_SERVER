<?php
/**
 * constant
 */
include_once 'constant.php';

/**
 * autoload
 */
include_once 'autoload.php';

/**
 * Composer Autoload
 */
include_once ROOT_PATH . 'vendor/autoload.php';

/**
 * Session Environment
 */
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 100);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 100);
// ini_set('session.save_path', SESSION_PATH);
// session_name( SESSION_NAME );
session_start();

/**
 * Closure 체크 함수
 */
if (! function_exists('is_closure')) {
    function is_closure($t) : bool {
        return !! ($t instanceof \Closure);
    }
}

/**
 * 앱의 설정 파일 로드
 */
if (! function_exists('app')) {
    function app(string $key = '') {
        return \onair\lib\AppFacade::getApp($key);
    }
}

/**
 * User 클래스
 */
if (! function_exists('user')) {
    function user() {
        return \onair\lib\AppFacade::getUser();
    }
}

/**
 * UserProfile 클래스
 */
if (! function_exists('userProfile')) {
    function userProfile() {
        return \onair\lib\AppFacade::getUserProfile();
    }
}

/**
 * getTagMatchHandler 클래스
 */
if (! function_exists('handleTagMatch')) {
    function handleTagMatch() {
        return \onair\lib\AppFacade::getTagMatchHandler();
    }
}

/**
 * handleDB
 * 
 * 모든 DB 인스턴트는 해당 메서드를 통해 처리한다
 */
if (! function_exists('handleDB')) {
    function handleDB(string $dbType = 'mongo') {
        return \onair\lib\AppFacade::getDBInstance($dbType);
    }
}

/**
 * handleRequest
 * 
 * 모든 $_POST 또는 $_GET의 변수값을 해당 메서드를 통해 처리한다
 */
if (! function_exists('handleRequest')) {
    function handleRequest(string $key) {
        return \onair\lib\AppFacade::getSecurityRequest('POST', $key);
    }
}

/**
 * FileHandler
 * 
 * 모든 파일을 컨트롤하고 업로드를 관리하는 클래스
 */
if (! function_exists('handleFile')) {
    function handleFile($files) {
        return \onair\lib\AppFacade::getFileHandler($files);
    }
}

/**
 * TagHandler
 * 
 * 모든 파일을 컨트롤하고 업로드를 관리하는 클래스
 */
if (! function_exists('handleTag')) {
    function handleTag() {
        return \onair\lib\AppFacade::getTagHandler();
    }
}

/**
 * handleHeader
 * 
 * $_SERVER 헤더에 담긴 키를 찾아 리턴한다
 */
if (! function_exists('handleHeader')) {
    function handleHeader(string $key) {
        $key = strtoupper($key);

        if (\array_key_exists($key, $_SERVER)) {
            return $_SERVER[ $key ];
        }

        return false;
    }
}

/**
 * static으로 선언한 클래스를 파괴한다
 */
if (! function_exists('eliminateHandler')) {
    function eliminateHandler(string $handlerName) : bool {
        return !! \onair\lib\AppFacade::eliminateHandler($handlerName);
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
            if ( is_array($args[0]) ) {
                return (object) $args[0];
            } 
            else {
                return $args[0];
            }
        } 
        else {
            $i = 0;
            $bulk = [];

            foreach ($args as $key => $v) {
                if ($v instanceof \onair\lib\InjectionSecurity) {
                    $bulk[ $v->getKey() ] = $v->get();
                } else {
                    $bulk[ $key ] = $v;
                }

                $i += 1;
            }

            return (object) $bulk;
        }
    }
}

/**
 * Easy 디버그 도구
 */
if (! function_exists('dd')) {
    function dd($data) {
        highlight_string("\n<?php\n\$data =\n" . var_export($data, true) . ";\n?>\n\n");
    }
}

/**
 * 유효한 세션이 있는지 확인한다
 */
if (! function_exists('sessionValid')) {
    function sessionValid() : bool {
        return !! app()->session('_id');
    }
}

/**
 * JSON으로 출력 후 로직을 종료시킴
 */
if (! function_exists('endpoint')) {
    function endpoint(string $message, int $code, array $options = []) : void {
        header('Content-Type: application/json');

        $bulk = [ 'code' => $code, 'explain' => $message ];

        if (count($options) > 0) {
            $bulk['options'] = $options;
        }

        echo json_encode($bulk, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit();
    }
}

/**
 * 컨트롤러 
 */
if (! function_exists('controller')) {
    function controller(string $ctrl) : \Closure {
        $file = CONTROLLER_PATH . $ctrl . '.php';

        if ( \file_exists($file) ) {
            $controllerClosure = include $file;

            if ( is_closure($controllerClosure) ) {
                return $controllerClosure;
            }
        }

        throw new \ErrorException("{$ctrl} :: 해당 컨트롤러를 찾을 수 없습니다");
    }
}

/**
 * 미들웨어
 */
if (! function_exists('middleware')) {
    function middleware(string $middle) : \Closure {
        $file = MIDDLEWARE_PATH . $middle . '.php';

        if ( \file_exists($file) ) {
            $middlewareClosure = include $file;

            if ( is_closure($middlewareClosure) ) {
                return $middlewareClosure;
            }
        }

        throw new \ErrorException("{$middle} :: 해당 미들웨어를 찾을 수 없습니다");
    }
}

/**
 * 현재 환경이 프로덕션 환경인지 체크
 */
if (! function_exists('isProd')) {
    function isProd() : bool {
        return !! ( $_SERVER['SERVER_NAME'] == 'byzz.app' );
    }
}

/**
 * Encrypt a message
 * 
 * https://stackoverflow.com/questions/16600708/how-do-you-encrypt-and-decrypt-a-php-string
 * 
 * @param string $message - message to encrypt
 * @return string
 * @throws RangeException
 */
function safeEncrypt(string $message): string {
    $key = app()->var('key');

    if (mb_strlen($key, '8bit') !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RangeException('Key is not the correct size (must be 32 bytes).');
    }

    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

    $cipher = base64_encode(
        $nonce.
        sodium_crypto_secretbox(
            $message,
            $nonce,
            $key
        )
    );
    sodium_memzero($message);
    sodium_memzero($key);
    return $cipher;
}

/**
 * Decrypt a message
 * 
 * https://stackoverflow.com/questions/16600708/how-do-you-encrypt-and-decrypt-a-php-string
 * 
 * @param string $encrypted - message encrypted with safeEncrypt()
 * @param string $key - encryption key
 * @return string
 * @throws Exception
 */
function safeDecrypt(string $encrypted): string {
    $key = app()->var('key');

    $decoded = base64_decode($encrypted);
    $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
    $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

    $plain = sodium_crypto_secretbox_open(
        $ciphertext,
        $nonce,
        $key
    );
    if (!is_string($plain)) {
        throw new Exception('Invalid MAC');
    }
    sodium_memzero($ciphertext);
    sodium_memzero($key);
    return $plain;
}