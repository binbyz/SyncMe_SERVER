<?php

include_once 'app.php';

/**
 * 로그인
 */
return function ($entityBody) 
{
    $entity = \toObject( json_decode($entityBody, JSON_FORCE_OBJECT) );

    if (! $entity->loginEmail || ! $entity->loginPassword) {
        endpoint( "로그인을 진행할 수 없습니다 (1)", app()::CODE_GLOBAL_FAILURE );
    }

    user()::login($entity->loginEmail, $entity->loginPassword);
};