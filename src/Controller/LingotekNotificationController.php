<?php

namespace Drupal\lingotek\Controller;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
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
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $content_translation_service */
    $content_translation_service = \Drupal::service('lingotek.content_translation');

    /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $config_translation_service */
    $config_translation_service = \Drupal::service('lingotek.config_translation');

    $translation_service = $content_translation_service;

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
        $entity = $this->getEntity($request->get('document_id'));
        /** @var LingotekProfile $profile */
        $profile = $this->getProfile($entity);
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $config_translation_service;
          }
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
        $entity = $this->getEntity($request->get('document_id'));
        /** @var LingotekProfile $profile */
        $profile = $this->getProfile($entity);
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $config_translation_service;
          }
          $http_status_code = Response::HTTP_OK;
          $locale = $request->get('locale');
          $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)->id();
          $result['set_target_status'] = $translation_service->setTargetStatus($entity, $langcode, Lingotek::STATUS_READY);
          $result['download'] = $profile->hasAutomaticDownloadForTarget($langcode) ?
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

  protected function getProfile($entity) {
    /** @var \Drupal\lingotek\LingotekConfigurationServiceInterface $configuration_service */
    $configuration_service = \Drupal::service('lingotek.configuration');
    $profile = NULL;
    if ($entity instanceof ContentEntityInterface) {
      $profile = $configuration_service->getEntityProfile($entity, FALSE);
    }
    elseif ($entity instanceof ConfigEntityInterface) {
      $profile = $configuration_service->getConfigEntityProfile($entity, FALSE);
    }
    return $profile;
  }

  protected function getEntity($document_id) {
    /** @var \Drupal\lingotek\LingotekContentTranslationServiceInterface $translation_service */
    $translation_service = \Drupal::service('lingotek.content_translation');
    $entity = $translation_service->loadByDocumentId($document_id);
    if ($entity === NULL) {
      /** @var \Drupal\lingotek\LingotekConfigTranslationServiceInterface $translation_service */
      $translation_service = \Drupal::service('lingotek.config_translation');
      $entity = $translation_service->loadByDocumentId($document_id);
    }
    return $entity;
  }
}
