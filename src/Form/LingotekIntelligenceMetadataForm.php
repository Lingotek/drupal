<?php

namespace Drupal\lingotek\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\lingotek\LingotekIntelligenceServiceConfig;

/**
 * Helper class for creating the form for setting up the intelligence metadata.
 *
 * @package Drupal\lingotek\Form
 */
class LingotekIntelligenceMetadataForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'lingotek.intelligence_metadata_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $metadata = $this->getIntelligenceMetadata($form_state);

    $form['intelligence_metadata'] = [
      '#type' => 'details',
      '#title' => $this->t('Lingotek Intelligence Metadata'),
      '#tree' => TRUE,
    ];

    $form['intelligence_metadata']['use_author'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Content\'s Author name and email to be tracked',
      '#default_value' => $metadata->getAuthorPermission(),
    ];

    $form['intelligence_metadata']['use_author_email'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'checkbox',
      '#title' => 'Enable the Content Author\'s email to be tracked',
      '#default_value' => $metadata->getAuthorEmailPermission(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_author]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_contact_email_for_author'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'checkbox',
      '#title' => 'Use the Contact Email as the Author Default Email',
      '#default_value' => $metadata->getContactEmailForAuthorPermission(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_author]"]' => ['checked' => TRUE],
          ':input[name="intelligence_metadata[use_contact_email]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['default_author_email'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Default Author Email'),
      '#description' => $this->t('Only used if the Author does not have an email address'),
      '#default_value' => $metadata->getDefaultAuthorEmail(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_author]"]' => ['checked' => TRUE],
          ':input[name="intelligence_metadata[use_author_email]"]' => ['checked' => TRUE],
          ':input[name="intelligence_metadata[use_contact_email_for_author]"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_business_unit'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Business Unit to be tracked',
      '#default_value' => $metadata->getBusinessUnitPermission(),
    ];

    $form['intelligence_metadata']['business_unit'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Business Unit'),
      '#default_value' => $metadata->getBusinessUnit(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_business_unit]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_business_division'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Business Division to be tracked',
      '#default_value' => $metadata->getBusinessDivisionPermission(),
    ];

    $form['intelligence_metadata']['business_division'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Business Division'),
      '#default_value' => $metadata->getBusinessDivision(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_business_division]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_campaign_id'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Campaign ID to be tracked',
      '#default_value' => $metadata->getCampaignIdPermission(),
    ];

    $form['intelligence_metadata']['campaign_id'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Campaign Id'),
      '#default_value' => $metadata->getCampaignId(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_campaign_id]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_campaign_rating'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Campaign Rating to be tracked',
      '#default_value' => $metadata->getCampaignRatingPermission(),
    ];

    $form['intelligence_metadata']['campaign_rating'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'number',
      '#title' => $this->t('Campaign Rating'),
      '#default_value' => $metadata->getCampaignRating(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_campaign_rating]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_channel'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Channel to be tracked',
      '#default_value' => $metadata->getChannelPermission(),
    ];

    $form['intelligence_metadata']['channel'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Channel'),
      '#default_value' => $metadata->getChannel(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_channel]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_contact_name'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Contact Name to be tracked',
      '#default_value' => $metadata->getContactNamePermission(),
    ];

    $form['intelligence_metadata']['contact_name'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Contact Name'),
      '#default_value' => $metadata->getContactName(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_contact_name]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_contact_email'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Contact Email to be tracked',
      '#default_value' => $metadata->getContactEmailPermission(),
    ];

    $form['intelligence_metadata']['contact_email'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Contact Email'),
      '#default_value' => $metadata->getContactEmail(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_contact_email]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_content_description'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Content Description to be tracked',
      '#default_value' => $metadata->getContentDescriptionPermission(),
    ];

    $form['intelligence_metadata']['content_description'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Content Description'),
      '#default_value' => $metadata->getContentDescription(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_content_description]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_base_domain'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Base Domain to be tracked',
      '#default_value' => $metadata->getBaseDomainPermission(),
      '#description' => 'This value will be pulled from the entities location and cannot be edited',
    ];

    $form['intelligence_metadata']['use_reference_url'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Reference URL to be tracked',
      '#default_value' => $metadata->getReferenceUrlPermission(),
      '#description' => 'This value will be pulled from the entities location and cannot be edited',
    ];

    $form['intelligence_metadata']['use_external_style_id'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the External Style ID to be tracked',
      '#default_value' => $metadata->getExternalStyleIdPermission(),
    ];

    $form['intelligence_metadata']['external_style_id'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('External Style Id'),
      '#default_value' => $metadata->getExternalStyleId(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_external_style_id]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_purchase_order'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Purchase Order to be tracked',
      '#default_value' => $metadata->getPurchaseOrderPermission(),
    ];

    $form['intelligence_metadata']['purchase_order'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Purchase Order'),
      '#default_value' => $metadata->getPurchaseOrder(),
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_purchase_order]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['intelligence_metadata']['use_region'] = [
      '#type' => 'checkbox',
      '#title' => 'Enable the Region to be tracked',
      '#default_value' => $metadata->getRegionPermission(),
    ];

    $form['intelligence_metadata']['region'] = [
      '#attributes' => ['class' => ['indented']],
      '#type' => 'textfield',
      '#size' => 20,
      '#title' => $this->t('Region'),
      '#default_value' => $metadata->getRegion() ?: '',
      '#states' => [
        'visible' => [
          ':input[name="intelligence_metadata[use_region]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $form['intelligence_metadata']['actions'] = ['#type' => 'actions'];
    $form['intelligence_metadata']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Lingotek Intelligence Metadata'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $metadata = $this->getIntelligenceMetadata($form_state);
    $metadata->setAuthorPermission($form_state->getValue(['intelligence_metadata', 'use_author']) ? TRUE : FALSE);
    $metadata->setAuthorEmailPermission($form_state->getValue(['intelligence_metadata', 'use_author_email']) ? TRUE : FALSE);
    $metadata->setContactEmailForAuthorPermission($form_state->getValue(['intelligence_metadata', 'use_contact_email_for_author']) ? TRUE : FALSE);
    $metadata->setDefaultAuthorEmail($form_state->getValue(['intelligence_metadata', 'default_author_email']));
    $metadata->setBusinessUnitPermission($form_state->getValue(['intelligence_metadata', 'use_business_unit']) ? TRUE : FALSE);
    $metadata->setBusinessUnit($form_state->getValue(['intelligence_metadata', 'business_unit']));
    $metadata->setBusinessDivisionPermission($form_state->getValue(['intelligence_metadata', 'use_business_division']) ? TRUE : FALSE);
    $metadata->setBusinessDivision($form_state->getValue(['intelligence_metadata', 'business_division']));
    $metadata->setCampaignIdPermission($form_state->getValue(['intelligence_metadata', 'use_campaign_id']) ? TRUE : FALSE);
    $metadata->setCampaignId($form_state->getValue(['intelligence_metadata', 'campaign_id']));
    $metadata->setCampaignRatingPermission($form_state->getValue(['intelligence_metadata', 'use_campaign_rating']) ? TRUE : FALSE);
    $metadata->setCampaignRating(intval($form_state->getValue(['intelligence_metadata', 'campaign_rating'])));
    $metadata->setChannelPermission($form_state->getValue(['intelligence_metadata', 'use_channel']) ? TRUE : FALSE);
    $metadata->setChannel($form_state->getValue(['intelligence_metadata', 'channel']));
    $metadata->setContactNamePermission($form_state->getValue(['intelligence_metadata', 'use_contact_name']) ? TRUE : FALSE);
    $metadata->setContactName($form_state->getValue(['intelligence_metadata', 'contact_name']));
    $metadata->setContactEmailPermission($form_state->getValue(['intelligence_metadata', 'use_contact_email']) ? TRUE : FALSE);
    $metadata->setContactEmail($form_state->getValue(['intelligence_metadata', 'contact_email']));
    $metadata->setContentDescriptionPermission($form_state->getValue(['intelligence_metadata', 'use_content_description']) ? TRUE : FALSE);
    $metadata->setContentDescription($form_state->getValue(['intelligence_metadata', 'content_description']));
    $metadata->setBaseDomainPermission($form_state->getValue(['intelligence_metadata', 'use_base_domain']) ? TRUE : FALSE);
    $metadata->setReferenceUrlPermission($form_state->getValue(['intelligence_metadata', 'use_reference_url']) ? TRUE : FALSE);
    $metadata->setExternalStyleIdPermission($form_state->getValue(['intelligence_metadata', 'use_external_style_id']) ? TRUE : FALSE);
    $metadata->setExternalStyleId($form_state->getValue(['intelligence_metadata', 'external_style_id']));
    $metadata->setPurchaseOrderPermission($form_state->getValue(['intelligence_metadata', 'use_purchase_order']) ? TRUE : FALSE);
    $metadata->setPurchaseOrder($form_state->getValue(['intelligence_metadata', 'purchase_order']));
    $metadata->setRegionPermission($form_state->getValue(['intelligence_metadata', 'use_region']) ? TRUE : FALSE);
    $metadata->setRegion($form_state->getValue(['intelligence_metadata', 'region']));

    // Show this message only if we are saving the general settings. For profiles
    // it's not needed.
    if ($metadata instanceof LingotekIntelligenceServiceConfig) {
      $this->messenger()->addStatus($this->t('Lingotek Intelligence Metadata saved correctly.'));
    }
  }

  /**
   * Helper method for getting the Lingotek intelligence metadata.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return LingotekIntelligenceMetadataInterface
   *   Returns the Lingotek intelligence metadata this form applies to.
   */
  protected function getIntelligenceMetadata(FormStateInterface $form_state) {
    $buildInfo = $form_state->getBuildInfo();
    return $buildInfo['args'][1];
  }

}
