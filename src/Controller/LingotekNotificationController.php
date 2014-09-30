<?php

namespace Drupal\lingotek\Controller;

use Drupal\lingotek\Controller\LingotekControllerBase;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekTranslatableEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekNotificationController extends LingotekControllerBase {

    public function endpoint(Request $request) {
        $request_method = $request->getMethod();
        parse_str($request->getQueryString(),$params);
        switch($type){
          case 'document_uploaded': // a document has finished importing
            //TO-DO: document uploaded and imported successfully for document_id
            $te = LingotekTranslatableEntity::loadByDocId($request->get('document_id'));
            $te->
            break;
          
          case 'target': // translation (i.e., chinese) has been completed for a document
            //TO-DO: download target for locale_code and document_id (also, progress and complete params would could be used as needed)
            //ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&locale_code=de-DE&document_id=bbf48a7b-b201-47a0-bc0e-0446f9e33a2f&complete=true&locale=de_DE&progress=100&type=target
            $te = LingotekTranslatableEntity::loadByDocId($request->get('document_id'));
            $te->setTargetStatus(Lingotek::STATUS_CURRENT);
            //download
            break;
          
          case 'project': // all translations for all documents have been completed for the project
            //ignore
            //ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&progress=100&type=project
            break;
          
          default:
            //ignore
            break;
          
        }
        //Lingotek::d($params);
        $http_status_code = Response::HTTP_NOT_IMPLEMENTED;
        $response = array(
            'message' => 'notify',
            'method' => $request_method,
            'params'=>$params
        );

        return JsonResponse::create($response, $http_status_code);
    }

}
