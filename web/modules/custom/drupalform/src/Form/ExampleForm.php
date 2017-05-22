<?php
/**
 * @file
 * Contains \Drupal\drupalform\Form\ExampleForm.
 **/

namespace Drupal\drupalform\Form;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
class ExampleForm extends FormBase {

    public function getFormId() {
        return 'drupalform_example_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        // Return array of Form API elements.
        $form['name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Full name'),
            '#required' => TRUE,
        ];

        $form['email'] = [
            '#type' => 'email',
            '#title' => t('Email'),
            '#required' => TRUE,
        ];

        $form['phone'] = [
            '#type' => 'tel',
            '#title' => t('Phone'),
            '#attributes' => ['placeholder' => t('+38000-000-00-00')],
            '#required' => TRUE,
        ];

        $form['subject'] = [
            '#type' => 'textfield',
            '#title' => t('Subject'),
        ];

        $form['message'] = [
            '#type' => 'textarea',
            '#title' => t('Message'),
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Save'),
        ];

        return $form;
  }

public function validateForm(array &$form,  FormStateInterface $form_state) {
    //if (!$form_state->isValueEmpty('name')) {
    if (Unicode::strlen($form_state->getValue('name')) <= 5) {
            $form_state->setErrorByName('name', t('Your name is less than 5 characters'));
        }
    if (!preg_match('/\b[\w.-]+@[\w.-]+\.[A-Za-z]{2,6}\b/', $form_state->getValue('email'))) {
            $form_state->setErrorByName('email', t('Invalid email format! Try like in example: mymail@mail.com'));
        }
    if (!preg_match("/^[+][0-9]{5}-[0-9]{3}-[0-9]{2}-[0-9]{2}+$/", $form_state->getValue('phone'))) {
        $form_state->setErrorByName('phone', $this->t('Invalid number! Please try enter like in example: +xxxxx-xxx-xx-xx'));
    }
}

public function submitForm(array &$form,  FormStateInterface $form_state) {
    // Validation covered in later recipe, required to satisfy
    $mailManager = \Drupal::service('plugin.manager.mail');
    $module = 'mymodule';
    $key = 'create_article';
    $to = $form_state->getValue('email');
    $params['phone'] = $form_state->getValue('phone');
    $params['message'] = $form_state->getValue('message');
    $params['subject'] = $form_state->getValue('subject');
    $params['email'] = $form_state->getValue('email');
    $params['name'] = $form_state->getValue('name');
    $params['node_title'] = 'title';
    $langcode = \Drupal::currentUser()->getPreferredLangcode();
    $send = TRUE;
    $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
    drupal_set_message(t('Success!'));
    if ($result['result'] !== TRUE) {
        drupal_set_message(t('There was a problem sending your message and it was not sent.'), 'error');
    }
}
}

