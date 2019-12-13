<?php

namespace onair\lib;

class UserProfile extends \onair\lib\User
{
    /**
     * 유저 프로파일 정보가 담기는 콜렉션 이름
     */
    static $_db_collection = 'syncme.user_profile';

    /**
     * 회원정보 업데이트
     *
     * @param array $udata
     * @param array $projection
     * @return void
     */
    static function update(array $udata) {
        $db = handleDB('mongo');
        $bulk = new \MongoDB\Driver\BulkWrite();

        // TODO 이렇게 하면 photo 필드만 나오지는 확인
        // `projection`
        $pProfile = self::get(
            app()->session('_id'),
            [ "projection" => ["photo" => 1] ]
        );

        $pProfile->photo[] = $udata;
        $bulk->update(
            [ "user_id" => app()->session('_id') ],
            [ "$set" => [ "photo" => $pProfile ] ],
            [ "$upsert" => true ]
        );

        return !! $db->executeBulkWrite(self::$_db_collection, $bulk);
    }

    /**
     * 몽고디비 _id로 해당 유저의 프로필 데이터를 가져옴
     *
     * @param string $_id
     * @param array $options
     * @return array
     */
    static function get(string $_id, array $options = []) {
        $db = handleDB('mongo');
        $where = [];

        $query = new \MongoDB\Driver\Query($where, $options);
        $rows = $db->executeQuery(self::$_db_collection, $query)->toArray();

        if ($rows) {
            $rows = $rows[0];
        }

        return $rows;
    }
}