<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Entity\Video;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validation;

//Dependencia para generacion de token
use App\Services\JwtAuth;

class UserController extends AbstractController
{


    private function resjson($data)
    {
        //Serializacion de datos: convertir objetos a objetos mas simples para convertir en json
        $json = $this->get('serializer')->serialize($data, 'json');

        //Response con http
        $response = new Response();

        //Asiganr contenido a la respuesta
        $response->setContent($json);

        //Indicar formato de respuesta
        $response->headers->set('Content-Type', 'application/json');

        //Devolver la respuesta
        return $response;
    }

    public function index(): Response
    {
        //Repositorio: Serie de metodos para la entidad
        $user_repo = $this->getDoctrine()->getRepository(User::class);
        $video_repo = $this->getDoctrine()->getRepository(Video::class);

        //Con el metodo findAll() Anida todos objetos se la necesidad de realizar joins
        $users = $user_repo->findAll();
        $videos = $video_repo->findAll();

        $user = $user_repo->find(1);


        /* foreach ($users as $user) {
            echo"<h1>{$user->getName()} {$user->getSurname()}</h1>";
            
            foreach($user->getVideos() as $video){
                
                echo"<p>{$video->getTitle()} - {$video->getUser()->getEmail()}</p>";
            }
        }
        die(); */

        return $this->resjson($videos);
    }

    public function register(Request $request)
    {
        //Recoger datos por post
        $json = $request->get('json', null);


        //Decodifcar json
        $params = json_decode($json, true);

        //Respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha creado',
        ];

        //Comprobar y validar datos
        if ($json != null) {
            $name = !empty($params['name']) ? $params['name'] : null;
            $surname = !empty($params['surname']) ? $params['surname'] : null;
            $email = !empty($params['email']) ? $params['email'] : null;
            $password = !empty($params['password']) ? $params['password'] : null;

            $validator = Validation::createValidator();

            $validate_email = $validator->validate($email, [new Email()]);


            if (!empty($email) && count($validate_email) == 0 && !empty($password) && !empty($name) && !empty($surname)) {


                //Si aprueba la validacion entonces se crea el objeto del usuario
                $user = new User();

                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new DateTime('now'));

                //Cifrar la contrasena
                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);

                //Comprobar si existe(evitar duplicados)
                $doctrine = $this->getDoctrine();
                $em = $doctrine->getManager();

                $user_repo = $doctrine->getRepository(User::class);
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));

                if (count($isset_user) == 0) {

                    //Guardar usuario
                    $em->persist($user);
                    //Ejecuta la consulta para guardar en la BD
                    $em->flush();
                    $data = [
                        'status' => 'success',
                        'code' => 200,
                        'message' => 'Usuario creado correctamente',
                        'user' => $user
                    ];
                } else {

                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Ya existe este usuario',
                    ];
                }

                //Si no existe se guarda en BN

                //Respuesta de JSON
                //return $this->resjson($data);

            } else {
                $data = [
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Validacion incorrecta',
                ];
            }
            return new JsonResponse($data);
        }
    }

    public function login(Request $request, JwtAuth $jwt_auth)
    {
        //obtención de datos
        $json = $request->get("json", null);
        $params = json_decode($json);
        //array por defecto
        $data = [
            "status" => "error",
            "code" => 200,
            "message" => "El usuario no se ha podido identificar correctamente."
        ];
        //comprobaciones y validación de datos
        if ($json != null) {
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);
            if (!empty($email) && !empty($password) && count($validate_email) == 0) {
                //cifrado de contraseña
                $pwd = hash("sha256", $password);
                //servicio JWT e identificación
                if ($gettoken) {
                    $singup = $jwt_auth->signup($email, $pwd, $gettoken);
                } else {
                    $singup = $jwt_auth->signup($email, $pwd);
                }
                return new jsonResponse($singup);
            }
        }

        //Si se devuelve bien entonces la respuesta con la operacion exitosa

        return new JsonResponse($data);
        //return $this->resjson($data);
    }

    public function edit(Request $request, JwtAuth $jwt_auth)
    {
        //Recoger cabecera de autenticacion 
        $token = $request->headers->get('Authorization');
        //Crear metodo para comprobar si el token es correcto
        $authCheck = $jwt_auth->checkToken($token);

        //Respuesta por defecto
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Usuaruio no actualizado'
        ];

        //Si el token es correcto hacer actualizacion del usuario

        if ($authCheck) {
            //Actualizar usuario

            //Conseguir entity manager
            $em = $this->getDoctrine()->getManager();
            //conseguir datos de usuario autenticado
            $identity = $jwt_auth->checkToken($token, true);


            //obtener el usuario actualizar completo
            $user_repo = $this->getDoctrine()->getRepository(User::class);

            $user = $user_repo->findOneBy(['id' => $identity->sub]);

            //Recoger datos por post, null para que si no llegan el valor sea nulo
            $json = $request->get('json', null);
            $params = json_decode($json);


            //Comprobar y valdiar datos
            //Comprobar y validar datos
            if ($json != null) {
                $name = !empty($params->name) ? $params->name : null;
                $surname = !empty($params->surname) ? $params->surname : null;
                $email = !empty($params->email) ? $params->email : null;

                $validator = Validation::createValidator();

                $validate_email = $validator->validate($email, [new Email()]);


                if (!empty($email) && count($validate_email) == 0 && !empty($name) && !empty($surname)) {



                    //Asignar nuevis datos al objeto de usuario
                    $user->setName($name);
                    $user->setSurname($surname);
                    $user->setEmail($email);
                    $user->setRole('ROLE_USER');
                    $user->setCreatedAt(new DateTime('now'));

                    //Comprobar duplicados
                    $isset_user = $user_repo->findBy(['email' => $email]);

                    if(count($isset_user) == 0 || $identity->email==$email){

                        //Guardar en BD
                        //Guardar usuario
                        $em->persist($user);
                        //Ejecuta la consulta para guardar en la BD
                        $em->flush();
                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'Usuario editado correctamente',
                            'user' => $user
                        ];
                    }else{
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'No se puede usar ese email',
                        ];
                    }


                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Validacion incorrecta',
                    ];
                }
                return new JsonResponse($data);
            }
        }
       

        return $this->resjson($data);
    }
}
