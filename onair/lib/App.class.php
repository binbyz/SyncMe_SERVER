<?php

namespace onair\lib;

/**
 * 앱을 관리하는 총괄 클래스
 */
class App
{
    /**
     * 글로벌 성공 코드
     */
    const CODE_GLOBAL_COMPLETE = 0x07;

    /**
     * 글로벌 실패 코드
     */
    const CODE_GLOBAL_FAILURE = 0x04;

    /**
     * 현재의 Datetime
     */
    var $currentDatetime;

    /**
     * 설정 파일이 담기는 배열
     *
     * @var array
     */
    private $configuration = [];

    /**
     * 현재의 requestUri
     *
     * @var string
     */
    static $requestUri;

    /**
     * Http 리스트 목록
     */
    static $supportHttpMethod = ['GET', 'POST', 'HEAD', 'OPTIONS', 'PUT', 'DELETE', 'TRACE', 'CONNECT'];

    /**
     * 쿠키 리스트
     */
    static $cookie;

    /**
     * 세션 리스트
     */
    static $session;

    /**
     * App 클래스의 생성자
     */
    function __construct() {
        // 루트 홈 디렉토리의 상위 디렉토리안 app.cfg.php 파일을 생성해준다
        // 환경변수를 다루기 위함이며, 노출되서는 안되는 값들을 넣어서 사용할 수 있다
        // 예를 들어 구글API의 키 값이라던지 ..
        // 호출은 \app()::->var('KEY_NAME'); 으로 할 수 있다
        $fileConfiguration = ROOT_PATH . 'app.cfg.php';

        if ( \file_exists($fileConfiguration) ) {
            $this->configuration = require $fileConfiguration;
        }

        // 모든 URL의 끝은 '/'로 끝나야 한다
        // 예를 들어 `localhost/a`의 주소는 `localhost/a/`로 이동되어야 한다
        static::$requestUri = rtrim($_SERVER['REQUEST_URI'], '\/') . '/';

        // 쿠키값 복사하여 사용
        static::$cookie = & $_COOKIE;

        // 세션값 복사하여 사용
        static::$session = & $_SESSION;

        // 현재의 시간 할당
        $this->currentDatetime = date('Y-m-d H:i:s');
    }

    /**
     * 컨피그 파일에 있는 변수의 값을 리턴함
     *
     * @param string $key
     * @return string
     */
    function var(string $key) : string {
        $key = trim($key);

        if ($key) {
            if (\array_key_exists($key, $this->configuration)) {
                return $this->configuration[ $key ];
            }
        }

        return '';
    }

    /**
     * Controller를 실행시키기 위한 Wrapper
     *
     * @param array $middlewares
     * @param array $controllers
     * @return boolean
     */
    static function HTTP(array $middlewares = [], array $controllers = []) : bool {
        // 다음 미들웨어 \Closure에 대한 파라메터
        // 미들웨어에서는 특정 조건을 만족시키지 못 하면 `return false;`로 로직을 중단해버린다
        // `$deliver` 처음에는 body를 읽어서 대입해준다
        $entityBody = file_get_contents('php://input');

        foreach ($middlewares as $mw) {
            if ( \is_callable($mw) ) {
                if (false === ($entityBody = $mw( $entityBody ))) {
                    // 중간 미들웨어에서 유효하지 않으면
                    // 403 헤더 종료
                    http_response_code(403);
                    die('Middleware Forbidden');
                }
            }
        }
        
        foreach ($controllers as $ctrl) {
            if ( \is_callable($ctrl) && $ctrl() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * __capturePath
     *
     * @return bool
     */
    private static function __capturePath(string $path) : bool {
        $path = rtrim($path, '\/') . '/';

        if (static::$requestUri == $path) {
            return true;
        }

        return false;
    }

    /**
     * Http 요청을 실행하기 위한 메서드 
     *
     * @param string GET|POST|HEAD|OPTIONS|PUT|DELETE|TRACE|CONNECT
     * @param \Closure $router
     * @return bool
     */
    private static function __http(string $method, string $path, \Closure $router) : bool {
        if ($_SERVER['REQUEST_METHOD'] === $method && static::__capturePath($path)) {
            if (is_callable($router)) {
                $entityBody = file_get_contents('php://input');

                $router( $entityBody );

                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Method POST
     *
     * @param string $path
     * @param \Closure $router
     * @return \Closure
     */
    static function POST(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('POST', $path, $router);
        };
    }

    /**
     * Method GET
     *
     * @param string $path
     * @param \Closure $router
     * @return \Closure
     */
    static function GET(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('GET', $path, $router);
        };
    }

    /**
     * Method PUT
     *
     * @param string $path
     * @param \Closure $router
     * @return bool
     */
    static function PUT(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('PUT', $path, $router);
        };
    }

    /**
     * Method DELETE
     *
     * @param string $path
     * @param \Closure $router
     * @return bool
     */
    static function DELETE(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('DELETE', $path, $router);
        };
    }

    /**
     * Method HEAD
     *
     * @param string $path
     * @param \Closure $router
     * @return bool
     */
    static function HEAD(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('HEAD', $path, $router);
        };
    }

    /**
     * Method OPTIONS
     *
     * @param string $path
     * @param \Closure $router
     * @return bool
     */
    static function OPTIONS(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('OPTIONS', $path, $router);
        };
    }

    /**
     * TRACE
     *
     * @param string $path
     * @param \Closure $router
     * @return bool
     */
    static function TRACE(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('TRACE', $path, $router);
        };
    }

    /**
     * Method CONNECT
     *
     * @param string $path
     * @param \Closure $router
     * @return bool
     */
    static function CONNECT(string $path = '/', \Closure $router) : \Closure {
        return function () use ($path, $router) {
            return static::__http('CONNECT', $path, $router);
        };
    }

    /**
     * 현재 세션의 해당하는 key를 돌려줌
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    static function session(string $key = '', string $value = '') : string {
        if ( \array_key_exists($key, static::$session) ) {
            return self::$session[ $key ];
        } else {
            if ($value) {
                self::$session[$key] = $value;
                return self::$session[ $key ];
            }
        }

        return '';
    }

    /**
     * 쿠키 $key의 해당하는 값을 돌려줌
     *
     * @param string $key
     * @return string
     */
    static function cookie(string $key = '') : string {
        if ( \array_key_exists($key, static::$cookie) ) {
            return self::$cookie[ $key ];
        }

        return '';
    }
}