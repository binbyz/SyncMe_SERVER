<?php

namespace onair\lib;

class User 
{

    /**
     * 상태코드
     * 
     * 해당 유저가 이미 존재하면 해당 코드를 클라이언트에 전송
     */
    const CODE_ERROR = 0x04;

    /**
     * 상태코드
     * 
     * 실행이 정상적으로 되었을 때의 완료코드
     */
    const CODE_COMPLETE = 0x07;

    /**
     * 유저의 상태: 활성화
     */
    const STATUS_ACTIVE = 0x1;

    /**
     * 유저의 상태: 탈퇴
     */
    const STATUS_DEACTIVE = 0x0;

    /**
     * 유저의 상태: 블럭된 상태
     */
    const STATUS_BLOCKED = 0x04;

    /**
     * 유저 정보가 담기는 콜렉션 이름
     */
    static $_db_collection = 'syncme.user';

    /**
     * 해당 이메일로 유저가 가입되어 있는지 확인한다
     *
     * @param string $email
     * @return boolean
     */
    static function isExists(string $email) : bool {
        $db = handleDB('mongo');

        $query = new \MongoDB\Driver\Query([ 'email' => $email ]);
        $rows = $db->executeQuery(static::$_db_collection, $query)->toArray();

        return !! (
            (count($rows) != 0) ? true : false
        );
    }

    /**
     * 회원가입 메서드
     *
     * @param array $entity
     * @return boolean
     */
    static function join(array $entity) {
        $db = handleDB('mongo');

        if ( \array_key_exists('age', $entity) ) {
            $age = intval($entity['age']);

            if ($age >= 18 && $age <= 90) {
                $entity['age'] = $age;
            } else {
                return false;
            }
        }

        if (\array_key_exists('password', $entity)) {
            $entity['password'] = safeEncrypt( $entity['password'] );
        }

        if (! \array_key_exists('like', $entity)) {
            $entity['like'] = 0;
        }

        if (! \array_key_exists('dislike', $entity)) {
            $entity['dislike'] = 0;
        }

        // UTC TimeZone 저장 (#4)
        // UTC 시간으로 저장 후 출력에서는 UTC+09, KST 시간으로 변환하여 출력
        if (! \array_key_exists('timestamp', $entity)) {
            $entity['timestamp'] = new \MongoDB\BSON\UTCDateTime();
        }

        $entity['oauth_token'] = static::getOAuthToken( $entity['email'] );
        $entity['is_active'] = static::STATUS_ACTIVE;

        $bulk = new \MongoDB\Driver\BulkWrite();
        $bulk->insert($entity);

        if ($db->executeBulkWrite(static::$_db_collection, $bulk)) {
            return $entity['oauth_token'];
        } 

        return false;
    }

    /**
     * 로그인 로직 구현 (세션이용)
     * 
     * 다른 확장 가능방향이 생기면 추 후에 수정하는 방향으로 고민
     *
     * @param string $email
     * @param string $password
     * @return void endpoint로 json 데이터 출력
     */
    static function login(string $email = '', string $password = '') : void {
        $user = [];
        // $token = str_replace(
        //     '\/',
        //     '/',
        //     $token
        // );

        if ($email && $password) {
            $user = user()::get('login', ['email' => $email,  'password' => $password]);

            if (count($user) == 1) {
                $user = $user[0];

                $_SESSION['_id']         = $user->_id;
                $_SESSION['email']       = $user->email;
                $_SESSION['nofriend']    = $user->nofriend;
                $_SESSION['gcpid']       = $user->gcpid;
                $_SESSION['timestamp']   = $user->timestamp;
                $_SESSION['oauth_token'] = $user->oauth_token;
                $_SESSION['is_active']   = $user->is_active;

                if ($profile = \userProfile()::get()) {
                    $_SESSION['profile_age'] = $profile->age ?: null;
                    $_SESSION['profile_nickname'] = $profile->nickname ?: null;
                }

                $_SESSION['last_login']  = app()->currentDatetime;

                endpoint("LOGIN_SUCCESS", user()::CODE_COMPLETE, ["_id" => (string) $user->_id]);
            } else {
                session_destroy();

                endpoint(
                    "LOGIN_FAILURE_WITH_TOKEN_AND_NOT_SAME_EMAIL_PASSWORD", 
                    user()::CODE_ERROR
                );
            }
        }

        endpoint("LOGIN_FAILURE_DONT_HAVE_ID_PASSWORD", app()::CODE_GLOBAL_FAILURE);
    }

    /**
     * 로그아웃 처리 (세션제거)
     *
     * @return boolean
     */
    public static function logout() : bool {
        $_SESSION = null;
        $_SESSION = [];

        \session_destroy();

        return true;
    }

    /**
     * 이메일로 인증 토큰을 생성해 줌
     *
     * @param string $email
     * @return string
     */
    private static function getOAuthToken(string $email) : string {
        return \safeEncrypt(
            md5(strrev($email) . time())
        );
    }

    /**
     * 토큰으로 현재 유저가 활동 가능한 상태인지 체크
     *
     * @param string $token
     * @return boolean
     */
    static function isActive(string $token) : bool {
        $db = handleDB('mongo');

        // TODO 상태 체크는 항상 사용되어야 하는 쿼리이기 때문에
        // 해당 Query 객체를 static으로 선언해 두는 것도 좋을 것 같다
        $query = new \MongoDB\Driver\Query([ 'is_active' => static::STATUS_ACTIVE, 'oauth_token' => $token ]);
        $rows = $db->executeQuery(static::$_db_collection, $query)->toArray();

        return !! (
            count($rows) > 0 ? true : false
        );
    }

    /**
     * 유저 데이터를 구함
     *
     * @param string $key token|oauth_token|login
     * @param string $token
     * @param array $options
     * @return array
     */
    static function get(string $key = 'session', array $token = [], array $options = []) : array {
        $key = strtolower(trim($key));
        $db = handleDB('mongo');
        $where = [];

        switch ($key) {
            case 'token':
            case 'oauth_token':
                $where = [ 'oauth_token' => current($token) ];
            break;
            case 'login':
                $where = [ 'email' => $token['email'] ];
            break;
            default:
                $where = [ 'email' => app()->session('email') ];
        }

        $where = array_merge([ 'is_active' => static::STATUS_ACTIVE ], $where);

        $query = new \MongoDB\Driver\Query( $where, $options );
        $rows = $db->executeQuery(static::$_db_collection, $query)->toArray()[0];

        if ($key == 'login') {
            $oneUser = $rows;

            $copyPassword = $oneUser->password;
            $oneUser->password = \safeDecrypt( $oneUser->password );

            if ($oneUser->password != $token['password']) {
                // remove userdata
                $rows = [];
            } else {
                $oneUser->password = $copyPassword;
            }
        }

        return $rows;
    } 

    /**
     * 마지막 위치로 유저의 정보를 업데이트 한다
     *
     * @param double $latitude
     * @param double $longtitude
     * @return boolean
     */
    function updateLastLocation(float $longtitude, float $latitude) : bool {
        $db = handleDB('mongo');
        $bulk = new \MongoDB\Driver\BulkWrite();

        $bulk->update(
            [ '_id' => new \MongoDB\BSON\ObjectId( app()->session('_id') ) ],
            [ '$set' =>[
                'location' => 
                    [ 
                        'type'        => 'Point',
                        'coordinates' => [$longtitude, $latitude] ]
                    ]
            ]
        );

        return !! $db->executeBulkWrite(self::$_db_collection, $bulk);
    }

}