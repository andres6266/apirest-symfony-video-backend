<?php

namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;

class JwtAuth
{
    public $manager;
    public $key;

    //Disponible para realizar consutal en las bases de datos
    public function __construct($manager)
    {
        $this->manager = $manager;
        $this->key = 'master_full_stack';
    }

    public function signup($email, $password, $gettoken = null)
    {
        //Comprobar si el usuario existe
        $user = $this->manager->getRepository(User::class)->findOneBy([
            'email' => $email,
            'password' => $password
        ]);

        $signup = false;

        if (is_object($user)) {
            $signup = true;
        }

        //Generar el token si existe el usuario
        if ($signup) {
            $token = [
                'sub' => $user->getId(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'email' => $user->getEmail(),
                //Tiempo en el que se ha creado el token (hora actak)
                'iat' => time(),
                //expiracion de token
                //time +1semana*24horas*60min*60segundos
                'exp' => time() + (7 * 24 * 60 * 60)
            ];

            //Comprobar el flag gettoken

            //Generar el token
            $jwt = JWT::encode($token, $this->key, 'HS256');
            //Si el gettoken no esta vacio entonces recibimos el token
            if (!empty($gettoken)) {

                $data = $jwt;
            } else {
                $decoded = JWT::decode($jwt, $this->key, ['HS256']);
                $data = $decoded;
            }
        } else {
            $data = [
                'status' => 'error',
                'message' => 'Login Incorrecto',
            ];
        }


        //Devolver los datos
        return $data;
    }

    public function checkToken($jwt, $identity=false)
    {

        $auth = false;

        try {
            //code...
            $decoded = JWT::decode($jwt, $this->key, ['HS256']);
            if ($decoded && !empty($decoded) && is_object($decoded) && isset($decoded->sub)) {
                $auth = true;
            } else {

                $auth = false;
            }

            if($identity!=false ){
                return $decoded;
            }else{
                return $auth;
            }
        } catch (\UnexpectedValueException $e) {
            $auth = false;
        } catch (\DomainException $e) {
            $auth = false;
        }
        
        return $auth;
    }
}
