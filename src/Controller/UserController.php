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

                if (count($isset_user)== 0) {

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
}
