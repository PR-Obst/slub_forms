<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Alexander Bigga <alexander.bigga@slub-dresden.de>, SLUB Dresden
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 *
 *
 * @package slub_forms
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 *
 */
class Tx_SlubForms_Controller_EmailController extends Tx_SlubForms_Controller_AbstractController {

	/**
	 * @var Tx_Extbase_SignalSlot_Dispatcher
	 * @inject
	 */
	protected $signalSlotDispatcher;

	/**
	 * action list
	 *
	 * @return void
	 */
	public function listAction() {
		$emails = $this->emailRepository->findAll();
		$this->view->assign('emails', $emails);
	}

	/**
	 * action show
	 *
	 * @param Tx_SlubForms_Domain_Model_Email $email
	 * @return void
	 */
	public function showAction(Tx_SlubForms_Domain_Model_Email $email) {
		$this->view->assign('email', $email);
	}

	/**
	 * action new
	 *
	 * @param Tx_SlubForms_Domain_Model_Email $newEmail
	 * @ignorevalidation $newEmail
	 * @return void
	 */
	public function newAction(Tx_SlubForms_Domain_Model_Email $newEmail = NULL) {

		$singleFormShortname = $this->getParametersSafely('form');

		if (!empty($singleFormShortname)) {

			/**
			 * $singleFormShortname may be string or integer (via realurl)
			 *
			 * realurl e.g. "slubforms/userform" --> uid of form
			 *
			 * "tx_slubforms_sf[form]=userform" --> userform
			 *
			 */
			if (t3lib_utility_Math::canBeInterpretedAsInteger($singleFormShortname)) {
				$singleForm = $this->formsRepository->findAllById($singleFormShortname);
			} else {
				$singleForm = $this->formsRepository->findByShortname($singleFormShortname);
			}
			// if no form is found getFirst() will return false and that's what we want
			$this->view->assign('singleForm', $singleForm->getFirst());

		}

		if (!empty($this->settings['formsSelection'])) {
			// show only forms selected in flexform
			$forms = $this->formsRepository->findAllByUidsTree(t3lib_div::intExplode(',', $this->settings['formsSelection'], TRUE));

			if (count($forms) == 1) {
				$this->view->assign('singleForm', $this->formsRepository->findAllByUids(t3lib_div::intExplode(',', $this->settings['formsSelection'], TRUE))->getFirst());
			}

		} else {
			// take all
			$forms = $this->formsRepository->findAll();
		}

		$this->view->assign('newEmail', $newEmail);
		$this->view->assign('forms', $forms);

	}

	/**
	 * action initializeNew
	 *
	 *
	 * @return void
	 */
	//~ public function initializeNewAction() {
		//~ $formId = $this->getParametersSafely('field');
		//~ t3lib_utility_Debug::debug($field, 'initializeNewAction: ... ');
		//~ $this->view->assign('formid', $formid);
	//~ }
	/**
	 * action initializeCreate
	 *
	 *
	 * @return void
	 */
	 public function initializeCreateAction() {

		 /* Avoid exception in TYPO3 4.7 because newEmail is not set. This is checked before createAction in
		  * the validator. Maybe this is gone in 6.2?
		  * --> if "field" is empty there is no reason to call the createAction
		  */

		 $field = $this->getParametersSafely('field');

		 if (empty($field)) {
			 $this->forward('new', 'Email', 'SlubForms', $requestArguments);
		 }
	 }

	/**
	 * action create
	 *
	 * @param Tx_SlubForms_Domain_Model_Email $newEmail
	 * @param array $field Field Values
	 * @validate $field Tx_SlubForms_Domain_Validator_FieldValidator
	 * @return void
	 */
	public function createAction(Tx_SlubForms_Domain_Model_Email $newEmail, array $field = array()) {

		$fieldParameter = $this->getParametersSafely('field');
		//~ t3lib_utility_Debug::debug($field, 'createAction: field... ');

		$form = $this->formsRepository->findAllById($newEmail->getForm())->getFirst();

		// walk through all fieldsets
		foreach($fieldParameter as $getfieldset => $getfields) {

			$fieldset = $this->fieldsetsRepository->findByUid($getfieldset);
			$allfields = $fieldset->getFields();

			foreach($allfields as $id => $field) {

				if (isset($getfields[$field->getUid()])) {
					// checkbox-value is only transmitted if checked but should be always in email content
					// the value (1/0) may be converted in a configured string (value = TRUE : FALSE)
					if ($field->getType() == 'Checkbox') {

						$config = $this->configToArray($field->getConfiguration());
						if (!empty($config['value'])) {
							$settingPair = explode(":", $config['value']);
							// take true value
							$content[$field->getTitle()] = ($getfields[$field->getUid()] == 1) ? $settingPair[0] : $settingPair[1];
						} else {

							$content[$field->getTitle()] = $getfields[$field->getUid()];

						}
					} else if ($field->getType() == 'Radio') {

						$config = $this->configToArray($field->getConfiguration());
						// radioOption = text of the value to choose : integer value
						if (!empty($config['radioOption'])) {

							foreach ($config['radioOption'] as $radioOption) {
								$settingPair = explode(":", $radioOption);
								// take true value
								if ((int)$settingPair[1] == (int)$getfields[$field->getUid()]) {
									$content[$field->getTitle()] = $settingPair[0];
								}
							}

						} else {

							$content[$field->getTitle()] = $getfields[$field->getUid()];

						}
					} else if ($field->getType() == 'File') {

						if (isset($_FILES['tx_slubforms_sf']) && ($_FILES['tx_slubforms_sf']['error']['field'][$getfieldset][$field->getUid()] == UPLOAD_ERR_OK)) {

							//~ t3lib_utility_Debug::debug($_FILES['tx_slubforms_sf'], '$createAction 2 $$_FILES:... ');

							$content[$field->getTitle()] = $_FILES['tx_slubforms_sf']['name']['field'][$getfieldset][$field->getUid()];

							$basicFileFunctions = t3lib_div::makeInstance('t3lib_basicFileFunctions');
							// get filename
							$fileName = $basicFileFunctions->getUniqueName(
								$_FILES['tx_slubforms_sf']['name']['field'][$getfieldset][$field->getUid()],
								t3lib_div::getFileAbsFileName('uploads/tx_slubforms/')
							);

							// copy temp file to uploads
							t3lib_div::upload_copy_move (
								$_FILES['tx_slubforms_sf']['tmp_name']['field'][$getfieldset][$field->getUid()],
								$fileName
							);
						} else {

							$content[$field->getTitle()] = '-';

						}
					} else {

						$content[$field->getTitle()] = empty($getfields[$field->getUid()]) ? '-' : $getfields[$field->getUid()];

					}

					if ($field->getIsSenderEmail()) {

						$senderEmail['address'] = $getfields[$field->getUid()];
						$senderEmail['required'] = $field->getRequired();

					} else if ($field->getIsSenderName()) {

						$senderName = $getfields[$field->getUid()];

					}

				} else if ($field->getType() == 'Checkbox') {

						$config = $this->configToArray($field->getConfiguration());

						if (!empty($config['value'])) {
							$settingPair = explode(":", $config['value']);
							// take false value
							$content[$field->getTitle()] = $settingPair[1];
						}
						else {
							$content[$field->getTitle()] = $getfields[$field->getUid()];
						}

				} else if ($field->getType() == 'Description') {

						continue;

				} else {

					$content[$field->getTitle()] = '-';

				}
			}

			//~ t3lib_utility_Debug::debug($getfields, 'createAction: getfields ... ');
			$contentText = '<ul>';
			foreach ($content as $fieldName => $value)
				$contentText .= '<li>'.$fieldName . ': <b>'. $value.'</b></li>';
			$contentText .= '</ul>';

			$newEmail->setContent(trim($contentText));

		}

		// check for senderEmail
		// It may be empty if no senderEmail-Field has been sent. This happens in case of the anonymous function which
		// disables the input fields
		if (!empty($senderEmail['address'])) {

			$newEmail->setSenderEmail($senderEmail['address']);

		} else {

			// check if extra anonymous field is set like session key editcode
			$anonymous = $this->getParametersSafely('anonymous');
			// if required is set, we ignore the anonymous session key anyhow. little stupid, but...
			if ($this->settings['anonymEmails']['allow'] && $this->settings['anonymEmails']['defaultEmailAddress']
				&& (($anonymous === $this->getSessionData('editcode')) || !$senderEmail['required'])) {

				$newEmail->setSenderEmail($this->settings['anonymEmails']['defaultEmailAddress']);

			} else {
				// we can't send an email without the senderEmail -->forward back to newAction
				$this->forward('new', NULL, NULL, array('form' => $form->getUid()));
			}
		}

		// check for senderName (once more)
		if (!empty($senderName))
			$newEmail->setSenderName($senderName);
		else
			// if nothing helps, we can send without the senderName but we have to set something.
			$newEmail->setSenderName('-');

		//~ t3lib_utility_Debug::debug($content, 'createAction: content... ');

		$settings = array();
		// add signal before sending Email
		$this->signalSlotDispatcher->dispatch(
			__CLASS__,
			'beforeEmailStorage',
			array($newEmail, $fieldParameter, &$settings)
		);

		if (! isset($settings['error'])) {
			// if there is any error detected, we won't send and store this mail.

			// email to customer
			// send only if "sendConfirmationEmailToCustomer" TS setting is true
			if ($this->settings['sendConfirmationEmailToCustomer'] && $newEmail->getSenderEmail() != $this->settings['anonymEmails']['defaultEmailAddress']) {
				$this->sendTemplateEmail(
					array($newEmail->getSenderEmail() => $newEmail->getSenderName()),
					array($this->settings['senderEmailAddress'] => Tx_Extbase_Utility_Localization::translate('slub-forms.senderEmailName', 'slub_forms') . ' - noreply'),
					Tx_Extbase_Utility_Localization::translate('slub-forms.senderSubject', 'slub_forms') . ' ' . $form->getTitle(),
					'ConfirmEmail',
					array(	'email' => $newEmail,
						'form' => $form,
						'content' => $content,
						'settings' => $this->settings,
					)
				);
			}

			// email to form owner
			$this->sendTemplateEmail(
				array($form->getRecipient() => ''),
				array($newEmail->getSenderEmail() => $newEmail->getSenderName()),
				Tx_Extbase_Utility_Localization::translate('tx_slubforms_domain_model_email.form', 'slub_forms') . ': ' . $form->getTitle() . ': '. $newEmail->getSenderName(). ', '. $newEmail->getSenderEmail() ,
				'FormEmail',
				array(	'email' => $newEmail,
					'form' => $form,
					'content' => $content,
					'filename' => $fileName,
					'settings' => $this->settings,
				)
			);

			$this->emailRepository->add($newEmail);

		}

		// reset session data
		$this->setSessionData('editcode', '');

		if (! empty($this->settings['pageShowForm'])) {

			$this->uriBuilder->setTargetPageUid($this->settings['pageShowForm']);
	//		$this->uriBuilder->setNoCache(TRUE);
			$this->uriBuilder->setUseCacheHash(FALSE);

			$newsUri = $this->uriBuilder->uriFor(
				'detail',
				array('news' => $settings['newsid'][0],
					'day' => $settings['newsid'][1],
					'month' => $settings['newsid'][2],
					'year' => $settings['newsid'][3]
					),
				'News',
				'news',
				'pi1');

			$this->redirectToURI($newsUri, 3, 303);
		}

		$this->view->assign('content', $content);
		$this->view->assign('form', $form);
		$this->view->assign('email', $newEmail);
	}

	/**
	 * action delete
	 *
	 * @param Tx_SlubForms_Domain_Model_Email $email
	 * @return void
	 */
	public function deleteAction(Tx_SlubForms_Domain_Model_Email $email) {
		$this->emailRepository->remove($email);
		$this->redirect('list');
	}

	/**
	 * Set session data
	 *
	 * @param $key
	 * @param $data
	 * @return
	 */
	public function setSessionData($key, $data) {

		$GLOBALS["TSFE"]->fe_user->setKey("ses", $key, $data);

		return;
	}

	/**
	 * Get session data
	 *
	 * @param $key
	 * @return
	 */
	public function getSessionData($key) {

		return $GLOBALS["TSFE"]->fe_user->getKey("ses", $key);
	}

	/**
	 * sendTemplateEmail
	 *
	 * @param array $recipient recipient of the email in the format array('recipient@domain.tld' => 'Recipient Name')
	 * @param array $sender sender of the email in the format array('sender@domain.tld' => 'Sender Name')
	 * @param string $subject subject of the email
	 * @param string $templateName template name (UpperCamelCase)
	 * @param array $variables variables to be passed to the Fluid view
	 * @return boolean TRUE on success, otherwise false
	 */
	protected function sendTemplateEmail(array $recipient, array $sender, $subject, $templateName, array $variables = array()) {

		if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) <  '6000000') {
			// TYPO3 4.7
			$emailViewHTML = $this->objectManager->create('Tx_Fluid_View_StandaloneView');
		} else {
			// TYPO3 6.x
			/** @var \TYPO3\CMS\Fluid\View\StandaloneView $emailViewHTML */
			$emailViewHTML = $this->objectManager->get('TYPO3\\CMS\\Fluid\\View\\StandaloneView');
		}

		$emailViewHTML->getRequest()->setControllerExtensionName($this->extensionName);
		$emailViewHTML->setFormat('html');
		$emailViewHTML->assignMultiple($variables);

		$extbaseFrameworkConfiguration = $this->configurationManager->getConfiguration(Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);
		$templateRootPath = t3lib_div::getFileAbsFileName($extbaseFrameworkConfiguration['view']['templateRootPath']);
		$partialRootPath = t3lib_div::getFileAbsFileName($extbaseFrameworkConfiguration['view']['partialRootPath']);

		$emailViewHTML->setTemplatePathAndFilename($templateRootPath . 'Email/' . $templateName . '.html');
		$emailViewHTML->setPartialRootPath($partialRootPath);


		if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) <  '6000000') {
			// TYPO3 4.7
			$message = t3lib_div::makeInstance('t3lib_mail_Message');
		} else {
			// TYPO3 6.x
			/** @var $message \TYPO3\CMS\Core\Mail\MailMessage */
			$message = $this->objectManager->get('TYPO3\\CMS\\Core\\Mail\\MailMessage');
		}

		$message->setTo($recipient)
				->setFrom($sender)
				->setCharset('utf-8')
				->setSubject($subject);

		// Plain text example
		$emailTextHTML = $emailViewHTML->render();
		$message->setBody($this->html2rest($emailTextHTML), 'text/plain');

		// HTML Email
		$message->addPart($emailTextHTML, 'text/html');

		if (!empty($variables['filename']))
		$message->attach(Swift_Attachment::fromPath($variables['filename']));

		$message->send();

		return $message->isSent();
	}

	/**
	 * html2rest
	 *
	 * this converts the HTML email to something Rest-Style like text form
	 *
	 * @param $htmlString
	 * @return
	 */
	public function html2rest($text) {

		$text = strip_tags( html_entity_decode($text, ENT_COMPAT, 'UTF-8'), '<br>,<p>,<b>,<h1>,<h2>,<h3>,<h4>,<h5>,<a>,<li>');
		// header is getting **
		$text = preg_replace('/<h[1-5]>|<\/h[1-5]>/', "**", $text);
		// bold is getting * ([[\w\ \d:\/~\.\?\=&%\"]+])
		$text = preg_replace('/<b>|<\/b>/', "*", $text);
		// get away links but preserve href with class slub-event-link
		$text = preg_replace('/(<a[\ \w\=\"]{0,})(class=\"slub-event-link\" href\=\")([\w\d:\-\/~\.\?\=&%]+)([\"])([\"]{0,1}>)([\ \w\d\p{P}]+)(<\/a>)/', "$6\n$3", $text);
		// Remove separator characters (like non-breaking spaces...)
		$text = preg_replace( '/\p{Z}/u', ' ', $text );
		$text = str_replace('<br />', "\n", $text);
		// get away paragraphs including class, title etc.
		$text = preg_replace('/<p[\s\w\=\"]*>(?s)(.*?)<\/p>/u', "$1\n", $text);
		$text = str_replace('<li>', "- ", $text);
		$text = str_replace('</li>', "\n", $text);
		// remove multiple spaces
		$text = preg_replace('/[\ ]{2,}/', '', $text);
		// remove multiple tabs
		$text = preg_replace('/[\t]{1,}/', '', $text);
		// remove more than one empty line
		$text = preg_replace('/[\n]{3,}/', "\n\n", $text);
		// remove all remaining html tags
		$text = strip_tags($text);

		return $text;
	}

	/**
	 *
	 * @param string $config
	 *
	 * @return array configuration
	 *
	 */
	private function configToArray($config) {

		$configSplit = explode("\n", $config);
		foreach ($configSplit as $id => $configLine) {
			$settingPair = explode("=", $configLine);
			switch (trim($settingPair[0])) {
				case 'radioOption': $configArray[trim($settingPair[0])][] = trim($settingPair[1]);
					break;
				case 'value':
				default: 		$configArray[trim($settingPair[0])] = trim($settingPair[1]);
					break;
			}
		}
		return $configArray;
	}

}

?>
