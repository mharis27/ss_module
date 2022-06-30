<?php

namespace Drupal\ss_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Core\File\Exception\FileException;

/**
 * Implements a custom form.
 */
class SsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'site_settings';
  }

  /**
   * Custom form generation
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // File Upload Validator
    $validators = [
      'file_validate_is_image' => [],
      'file_validate_extensions' => ['png gif jpg jpeg svg'],
      'file_validate_size' => [Environment::getUploadMaxSize()],
    ];

    $form = [
      '#attributes' => ['enctype' => 'multipart/form-data'],
    ];

    $form['siteName'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Site Name'),
      '#required' => TRUE,
      '#default_value' => \Drupal::configFactory()->getEditable('system.site')->get('name'),
      '#description' => $this->t("Please enter your site name"),
      '#weight' => 0,
    );

    $form['logo']['settings']['logo_upload'] = array(
      '#type' => 'file',
      '#title' => $this->t('Upload logo image'),
      '#maxlength' => 40,
      '#description' => $this->t("Please upload your site logo"),
      '#upload_validators' => $validators,
      '#upload_location' => 'public://files',
      '#weight' => 1,
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('submit'),
      '#button_type' => 'primary',
      '#weight' => 2,
    );

    return $form;
  }

  /**
   * Custom form validiation
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);

    // Check for site name not less than 3 characters or digits
    if (strlen($form_state->getValue('siteName')) < 3) {
      $form_state->setErrorByName(
        'siteName',
        $this->t('The site name is too short. Please enter a full site name.')
      );
    }

    // Check for uploaded logo and put temporary file in form values
    if (isset($form['logo'])) {
      $file = _file_save_upload_from_form($form['logo']['settings']['logo_upload'], $form_state, 0);
      if ($file) {
        $form_state->setValue('logo_upload', $file);
      } else {
        // File upload failed.
        $form_state->setErrorByName('logo_upload', $this->t('The logo could not be uploaded.'));
      }
    }

  }

  /**
   * Custom form submittion
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Setting up the site name
    \Drupal::configFactory()->getEditable('system.site')
      ->set('name', $form_state->getValue('siteName'))
      ->save();
    $this->messenger()->addMessage($this->t('Site Name Updated'));

    // Setting up the uploaded image as Logo
    $file_system = \Drupal::service('file_system');
    $default_scheme = \Drupal::config('system.file')->get('default_scheme');
    $themeName = \Drupal::theme()->getActiveTheme()->getName();
    $varName = 'theme_' . $themeName . '_settings';

    try {
      if (!empty($form_state->getValue('logo_upload'))) {
        $filename = $file_system->copy($form_state->getValue('logo_upload')->getFileUri(), $default_scheme . '://');
        \Drupal::configFactory()->getEditable('system.site')
          ->set($varName["logo_path"], set_include_path($filename))
          ->save();
        \Drupal::configFactory()->getEditable('system.site')
          ->set('logo_image_style_logo_path', set_include_path($filename))
          ->save();
        $this->messenger()->addMessage($this->t('Logo is set to file ' . set_include_path($filename)));
      }
    } catch (FileException $e) {
      $this->messenger()->addMessage($this->t('Error ' . $e));
    }

  }
}

?>
