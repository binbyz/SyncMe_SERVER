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

        if ( \array_key_exists('password', $entity) ) {
            $entity['password'] = safeEncrypt( $entity['password'] );
        }

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
     * 이메일로 인증 토큰을 생성해 줌
     *
     * @param string $email
     * @return string
     */
    static function getOAuthToken(string $email) : string {
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

}