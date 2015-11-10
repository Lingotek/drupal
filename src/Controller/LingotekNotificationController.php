<?php

namespace Drupal\lingotek\Controller;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\lingotek\Entity\LingotekProfile;
use Drupal\lingotek\LanguageLocaleMapperInterface;
use Drupal\lingotek\Lingotek;
use Drupal\lingotek\LingotekLocale;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for lingotek module setup routes.
 */
class LingotekNotificationController extends LingotekControllerBase {

  public function endpoint(Request $request) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');

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
        break;
      
      case 'document':

        break;

      case 'document_uploaded': // a document has uploaded and imported successfully for document_id
        $entity = $translation_service->loadByDocumentId($request->get('document_id'));
        $profile_id = $entity->lingotek_profile->target_id;
        /** @var LingotekProfile $profile */
        $profile = LingotekProfile::load($profile_id);
        if ($entity) {
          $http_status_code = Response::HTTP_OK;
          $translation_service->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          $result['request_translations'] = ($profile->hasAutomaticUpload()) ?
             $translation_service->requestTranslations($entity) : [];
        } else {
          $http_status_code = Response::HTTP_NOT_FOUND;
        }
        break;

      case 'target': // translation (i.e., chinese) has been completed for a document
        //TO-DO: download target for locale_code and document_id (also, progress and complete params can be used as needed)
        //ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&locale_code=de-DE&document_id=bbf48a7b-b201-47a0-bc0e-0446f9e33a2f&complete=true&locale=de_DE&progress=100&type=target
        $entity = $translation_service->loadByDocumentId($request->get('document_id'));
        $profile_id = $entity->lingotek_profile->target_id;
        /** @var LingotekProfile $profile */
        $profile = LingotekProfile::load($profile_id);
        if ($entity) {
          $http_status_code = Response::HTTP_OK;
          $locale = $request->get('locale');
          $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->id();
          $result['set_target_status'] = $translation_service->setTargetStatus($entity, $langcode, Lingotek::STATUS_READY);
          $result['download'] = $profile->hasAutomaticDownload() ?
            $translation_service->downloadDocument($entity, $locale) : FALSE;
        } else {
          $http_status_code = Response::HTTP_NOT_FOUND;
          $messages[] = "Document not found.";
        }
        break;

      case 'phase':

        break;

      default: //ignore
        $http_status_code = Response::HTTP_NOT_IMPLEMENTED;
        $messages[] = "Not implemented.";
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
