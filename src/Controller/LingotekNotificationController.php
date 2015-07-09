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
    $http_status_code = Response::HTTP_NOT_IMPLEMENTED;
    $type = $request->get('type');
    $result = array();
    $messages = array();
    $security_token = $request->get('security_token');
    if ($security_token == 1) {
      $http_status_code = Response::HTTP_NOT_IMPLEMENTED;
    }
    parse_str($request->getQueryString(), $params);
    switch ($type) {

      case 'project': // all translations for all documents have been completed for the project
      //ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&progress=100&type=project
      //break;
      default: //ignore
        $http_status_code = Response::HTTP_NOT_IMPLEMENTED;
        $messages[] = "Not implemented.";
        break;

      case 'document':

      break;

      case 'document_uploaded': // a document has uploaded and imported successfully for document_id
        $te = LingotekTranslatableEntity::loadByDocId($request->get('document_id'));
        if ($te) {
          $http_status_code = Response::HTTP_OK;
          $result['request_translations'] = $te->requestTranslations();
        } else {
          $http_status_code = Response::HTTP_NOT_FOUND;
        }
        break;

      case 'target': // translation (i.e., chinese) has been completed for a document
        //TO-DO: download target for locale_code and document_id (also, progress and complete params can be used as needed)
        //ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&locale_code=de-DE&document_id=bbf48a7b-b201-47a0-bc0e-0446f9e33a2f&complete=true&locale=de_DE&progress=100&type=target
        $te = LingotekTranslatableEntity::loadByDocId($request->get('document_id'));
        if ($te) {
          $http_status_code = Response::HTTP_OK;
          $result['set_target_status'] = $te->setTargetStatus(Lingotek::STATUS_READY);
          $result['download'] = $te->download();
        } else {
          $http_status_code = Response::HTTP_NOT_FOUND;
          $messages[] = "Document not found.";
        }
        break;

      case 'phase':

      break;
    }

    $response = array(
        'service' => 'notify',
        'method' => $request_method,
        'params' => $params,
        'result' => $result,
        'messages' => $messages
    );

    return JsonResponse::create($response, $http_status_code);
  }

}
