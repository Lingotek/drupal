<?php

namespace Drupal\lingotek\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\lingotek\Lingotek;
use Psr\Log\LogLevel;
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
    $http_status_code = Response::HTTP_ACCEPTED;
    $type = $request->get('type');
    $result = [];
    $messages = [];
    $security_token = $request->get('security_token');
    if ($security_token == 1) {
      $http_status_code = Response::HTTP_ACCEPTED;
    }
    parse_str($request->getQueryString(), $params);
    switch ($type) {

      // all translations for all documents have been completed for the project
      case 'project':
        // ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&progress=100&type=project
        break;

      case 'document':

        break;

      // a document has uploaded and imported successfully for document_id
      case 'document_uploaded':
        $entity = $this->getEntity($request->get('document_id'));
        /** @var \Drupal\lingotek\Entity\LingotekProfile $profile */
        $profile = $this->getProfile($entity);
        if ($entity) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $config_translation_service;
          }
          $http_status_code = Response::HTTP_OK;
          $translation_service->setSourceStatus($entity, Lingotek::STATUS_CURRENT);
          $result['request_translations'] = ($profile->hasAutomaticUpload()) ?
             $translation_service->requestTranslations($entity) : [];
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $messages[] = "Document not found.";
        }
        break;
      case 'target_deleted':
        /**
         * array(
         * 'community_id' => 'my_community_id',
         * 'complete' => 'true',
         * 'deleted_at' => '1536115104487',
         * 'deleted_by_user_id' => 'user_hash',
         * 'deleted_by_user_login' => 'user@example.com',
         * 'deleted_by_user_name' => 'Name Surname',
         * 'doc_cts' => '1536115021300',
         * 'doc_domain_type' => 'http://example.com',
         * 'doc_region' => '',
         * 'doc_status' => 'COMPLETE',
         * 'documentId' => 'document_tms_id',
         * 'document_id' => 'document_id',
         * 'locale' => 'ca_ES',
         * 'locale_code' => 'ca-ES',
         * 'original_project_id' => '0',
         * 'progress' => '100',
         * 'projectId' => 'project_tms_id',
         * 'project_id' => 'project_id_hash',
         * 'status' => 'COMPLETE',
         * 'targetId' => 'target_tms_id',
         * 'target_id' => 'target_hash',
         * 'type' => 'target_deleted',
         * )
         */
        $entity = $this->getEntity($request->get('document_id'));
        if ($entity !== NULL) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $config_translation_service;
          }
          $locale = $request->get('locale');
          $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)
            ->id();
          $user_login = $request->get('deleted_by_user_login');
          $translation_service->setTargetStatus($entity, $langcode, Lingotek::STATUS_UNTRACKED);
          $this->logger->log(LogLevel::DEBUG, 'Target @locale for entity @label deleted by @user_login', [
            '@locale' => $locale,
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);
          $http_status_code = Response::HTTP_OK;
          $messages[] = new FormattableMarkup('Target @locale for entity @label deleted by @user_login', [
            '@locale' => $locale,
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);

        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $document_id = $request->get('document_id');
          $user_login = $request->get('deleted_by_user_login');
          $locale = $request->get('locale');
          $this->logger->log(LogLevel::WARNING, 'Target @locale for document @document_id deleted by @user_login in the TMS, but document not found on the system.', [
            '@locale' => $locale,
            '@user_login' => $user_login,
            '@document_id' => $document_id,
          ]);
        }
        break;
      case 'document_deleted':
        /**
         * array(
         * 'community_id' => 'my_community_id',
         * 'complete' => 'true',
         * 'deleted_at' => '1536171165274',
         * 'deleted_by_user_id' => 'user_hash',
         * 'deleted_by_user_login' => 'user@example.com',
         * 'deleted_by_user_name' => 'Name Surname',
         * 'documentId' => 'document_tms_id',
         * 'document_id' => 'document_id',
         * 'original_project_id' => '0',
         * 'progress' => '100',
         * 'projectId' => 'project_tms_id',
         * 'project_id' => 'project_id_hash',
         * 'type' => 'document_deleted',
         * )
         */
        $entity = $this->getEntity($request->get('document_id'));
        if ($entity !== NULL) {
          if ($entity instanceof ConfigEntityInterface) {
            $translation_service = $config_translation_service;
          }
          $user_login = $request->get('deleted_by_user_login');
          $translation_service->deleteMetadata($entity);
          $this->logger->log(LogLevel::DEBUG, 'Document for entity @label deleted by @user_login in the TMS.', [
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);
          $http_status_code = Response::HTTP_OK;
          $messages[] = new FormattableMarkup('Document for entity @label deleted by @user_login in the TMS.', [
            '@user_login' => $user_login,
            '@label' => $entity->label(),
          ]);
        }
        else {
          $http_status_code = Response::HTTP_NO_CONTENT;
          $document_id = $request->get('document_id');
          $user_login = $request->get('deleted_by_user_login');
          $this->logger->log(LogLevel::WARNING, 'Document @document_id deleted by @user_login in the TMS, but not found on the system.', [
            '@user_login' => $user_login,
            '@document_id' => $document_id,
          ]);
        }
        break;
      case 'phase':
        // translation (i.e., chinese) has been completed for a document
      case 'target':
        // TO-DO: download target for locale_code and document_id (also, progress and complete params can be used as needed)
        // ex. ?project_id=103956f4-17cf-4d79-9d15-5f7b7a88dee2&locale_code=de-DE&document_id=bbf48a7b-b201-47a0-bc0e-0446f9e33a2f&complete=true&locale=de_DE&progress=100&type=target
        $document_id = $request->get('document_id');
        $locale = $request->get('locale');
        $langcode = $this->languageLocaleMapper->getConfigurableLanguageForLocale($locale)
          ->id();

        $lock = \Drupal::lock();
        $lock_name = __FUNCTION__ . ':' . $document_id;

        do {
          if ($lock->lockMayBeAvailable($lock_name)) {
            if ($held = $lock->acquire($lock_name)) {
              break;
            }
          }
          $lock->wait($lock_name, rand(1, 12));
        } while (TRUE);

        try {
          $entity = $this->getEntity($document_id);
          /** @var \Drupal\lingotek\Entity\LingotekProfile $profile */
          $profile = $this->getProfile($entity);
          if ($entity) {
            if ($entity instanceof ConfigEntityInterface) {
              $translation_service = $config_translation_service;
            }
            $translation_service->setTargetStatus($entity, $langcode, Lingotek::STATUS_READY);

            if ($profile->hasAutomaticDownloadForTarget($langcode) && $profile->hasAutomaticDownloadWorker()) {
              $queue = \Drupal::queue('lingotek_downloader_queue_worker');
              $item = [
                'entity_type_id' => $entity->getEntityTypeId(),
                'entity_id' => $entity->id(),
                'locale' => $locale,
                'document_id' => $document_id,
              ];
              $result['download_queued'] = $queue->createItem($item);
            }
            elseif ($profile->hasAutomaticDownloadForTarget($langcode) && !$profile->hasAutomaticDownloadWorker()) {
              $result['download'] = $translation_service->downloadDocument($entity, $locale);
            }
            else {
              $result['download'] = FALSE;
            }
            if (isset($result['download']) && $result['download']) {
              $messages[] = "Document downloaded.";
              $http_status_code = Response::HTTP_OK;
            }
            elseif (isset($result['download_queued']) && $result['download_queued']) {
              $messages[] = new FormattableMarkup('Download for target @locale in document @document has been queued.', [
                '@locale' => $locale,
                '@document' => $document_id,
              ]);
              $result['download_queued'] = TRUE;
              $http_status_code = Response::HTTP_OK;
            }
            else {
              $messages[] = new FormattableMarkup('No download for target @locale happened in document @document.', [
                '@locale' => $locale,
                '@document' => $document_id,
              ]);
              if (!$profile->hasAutomaticDownloadForTarget($langcode)) {
                $http_status_code = Response::HTTP_OK;
              }
              else {
                $http_status_code = Response::HTTP_SERVICE_UNAVAILABLE;
              }
            }
          }
          else {
            $http_status_code = Response::HTTP_NO_CONTENT;
            $messages[] = "Document not found.";
          }
        }
        catch (\Exception $exception) {
          $http_status_code = Response::HTTP_SERVICE_UNAVAILABLE;
          $messages[] = new FormattableMarkup('Download of target @locale for document @document failed', [
            '@locale' => $locale,
            '@document' => $document_id,
          ]);
        }
        finally {
          $lock->release($lock_name);
        }
        break;
      // ignore
      default:
        $http_status_code = Response::HTTP_ACCEPTED;
        return new HtmlResponse('It works, but nothing to look here.', $http_status_code);
      break;
    }

    $response = [
      'service' => 'notify',
      'method' => $request_method,
      'params' => $params,
      'result' => $result,
      'messages' => $messages,
    ];

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
