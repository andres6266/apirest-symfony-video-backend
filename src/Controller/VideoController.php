<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Video;
use App\Services\JwtAuth;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Knp\Component\Pager\PaginatorInterface;



class VideoController extends AbstractController
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


    public function index(Request $request, JwtAuth $jwt_auth, PaginatorInterface $paginator, EntityManagerInterface $em)
    {
        // Recoger la cabecera de autenticacion
        $token = $request->headers->get('Authorization');

        // Comprobar el token
        $authCheck = $jwt_auth->checkToken($token);

        //Si es valido

        if ($authCheck) {

            // Conseguir la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            //$em = $this->getDoctrine()->getManager();

            // Consulta para paginar 
            $dql = "SELECT v FROM App\Entity\Video v WHERE v.user = {$identity->sub} ORDER BY v.id DESC";

            $query = $em->createQuery($dql);

            // Obtener parametro page de la url
            $page = $request->query->getInt('page', 1);

            $items_per_page = 5;

            // Realizar paginacion
            $pagination = $paginator->paginate($query, $page, $items_per_page);



            $total = $pagination->getTotalItemCount();



            // Preparar array de datos para devolver

            $data = [
                'status' => 'success',
                'code' => 200,
                'total_items_count' => $total,
                'page_actual' => $page,
                'items_per_page' => $items_per_page,
                'total_pages' => ceil($total / $items_per_page),
                'videos' => $pagination->getItems(),
                'user_id' => $identity->sub
            ];
        } else {

            // Si falla devolver: 
            $data = [
                'status' => 'error',
                'code' => 400,
                'message' => 'Error al listar videos'
            ];
        }

        return new JsonResponse($data);
    }


    public function newVideo(Request $request, JwtAuth $jwt_auth, $id = null)
    {

        //Recoger cabecera de autenticacion 
        $token = $request->headers->get('Authorization');
        //Crear metodo para comprobar si el token es correcto
        $authCheck = $jwt_auth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Error al crear o actualizar video'
        ];

        if ($authCheck) {
            //Recoger datos por post
            $json = $request->get('json', null);


            //Decodifcar json
            $params = json_decode($json, true);



            //Comprobar y validar datos
            if ($json != null) {
                //conseguir datos de usuario autenticado
                $identity = $jwt_auth->checkToken($token, true);

                $user_sub = $identity->sub;


                $user_id = $user_sub != null ? $user_sub : null;
                $title = !empty($params['title']) ? $params['title'] : null;
                $description = !empty($params['description']) ? $params['description'] : null;
                $url = !empty($params['url']) ? $params['url'] : null;
                $status = !empty($params['status']) ? $params['status'] : null;


                if (!empty($title) && !empty($user_id)) {

                    //Si aprueba la validacion entonces se crea el objeto del usuario

                    $doctrine = $this->getDoctrine();
                    $em = $doctrine->getManager();

                    //Buscar el usuario logueado 
                    $user = $doctrine->getRepository(User
                    ::class)->findOneBy([
                        'id' => $user_id
                    ]);


                    if ($id == null) {
                        $video = new Video();

                        $video->setUser($user);
                        $video->setTitle($title);
                        $video->setDescription($description);
                        $video->setUrl($url);
                        $video->setStatus($status);
                        $video->setCreatedAt(new DateTime('now'));
                        $video->setUpdatedAt(new DateTime('now'));


                        //Comprobar si existe(evitar duplicados)




                        $video_repo = $doctrine->getRepository(Video::class);
                        $isset_video = $video_repo->findBy(array(
                            'title' => $title,
                            'url' => $url
                        ));

                        if (count($isset_video) == 0) {

                            //Guardar usuario
                            $em->persist($video);
                            //Ejecuta la consulta para guardar en la BD
                            $em->flush();
                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'Video creado correctamente',
                                'user' => $video
                            ];
                        } else {

                            $data = [
                                'status' => 'error',
                                'code' => 400,
                                'message' => 'Ya existe este video',
                            ];
                        }
                    } else {
                        $video = $this->getDoctrine()->getRepository(Video::class)->findOneBy([
                            'id' => $id,
                            'user' => $identity->sub
                        ]);

                        if ($video && is_object($video)) {

                            $video->setTitle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);
                            $video->setStatus($status);
                            $video->setUpdatedAt(new DateTime('now'));

                            $em->persist($video);
                            $em->flush();

                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'El video se ha actualizado',
                                'video' => $video
                            ];
                        }
                    }



                    return $this->resjson($data);
                }
            }
        }

        return new JsonResponse($data);
    }


    public function detail_video(Request $request, JwtAuth $jwt_auth, Video $video)
    {

      

        //Obtener token  y comprobar si es correcto
        $token = $request->headers->get('Authorization');

        $authCheck = $jwt_auth->checkToken($token);

       

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Error en el video detalle o el video no te pertenece'
        ];
        
        if ($authCheck) {
            //Sacar la identidad del usuario
            $identity = $jwt_auth->checkToken($token, true);

            

            //Comprobar si el video  existe y es propiedad del usuario identificado
            if ($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {


                //Devolver una respuesta
                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                ];
            }
        }


        return $this->resjson($data);
    }

    public function delete_video(Request $request, JwtAuth $jwt_auth, Video $video)
    {
        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'Error en la eliminacion'
        ];

        $token = $request->headers->get('Authorization');

        $authCheck = $jwt_auth->checkToken($token);

        if ($authCheck) {
            $identity = $jwt_auth->checkToken($token, true);

            $doctrine = $this->getDoctrine();
            $em = $doctrine->getManager();



            if ($video && is_object($video) && $identity->sub == $video->getUser()->getId()) {
                $em->remove($video);
                $em->flush();

                $data = [
                    'status' => 'success',
                    'code' => 200,
                    'video' => $video
                ];
            }
        }

        return $this->resjson($data);
    }
}
