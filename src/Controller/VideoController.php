<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
    
    
}
