<?php
/**
 * @file
 * Contains \Drupal\recurring_import\Form\ImportForm.
 */
namespace Drupal\recurring_import\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\file\Entity\File;
use Drupal\Component\Utility\Xss;

class ImportForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'recurring_import_form';
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['description'] = array(
      '#markup' => '<p>Use this form to upload a CSV file of School Data</p>',
    );

    $form['import_csv'] = array(
      '#type' => 'managed_file',
      '#name' => 'my_file',
      '#title' => t('Upload file here'),
      '#upload_location' => 'public://recurring-csv',
      '#default_value' => '',
      '#upload_validators'  => array("file_validate_extensions" => array("csv")),
      '#states' => array(
        'visible' => array(
          ':input[name="File_type"]' => array('value' => t('Upload Your File')),
        ),
      ),
    );

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Upload CSV'),
      '#button_type' => 'primary',
    );

    return $form;
  }


    // public function validateForm (array &$form, FormStateInterface $form_state) {
         
    //   $values = $form_state->getValues();

    //   if(empty($values)) {
    //       $form_state->setErrorByName('my_file', t('File is empty.'));
         
    //   }
      
    // }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {


    /* Fetch the array of the file stored temporarily in database */
    $csv_file = $form_state->getValue('import_csv');

    if(!empty($csv_file)){
      /* Load the object of the file by it's fid */
      $file = File::load( $csv_file[0] );

      /* Set the status flag permanent of the file object */
      $file->setPermanent();

      /* Save the file in database */
      $file->save();

     // You can use any sort of function to process your data. The goal is to get each 'row' of data into an array
      // If you need to work on how data is extracted, process it here.
      $data = $this->csvtoarray($file->getFileUri(), ',');

      if(!empty($data)) {
          foreach($data as $row) {
            $operations[] = ['\Drupal\recurring_import\addImportContent::addImportContentItem', [$row]];
          }

          $batch = array(
            'title' => t('Updating Data...'),
            'operations' => $operations,
            'init_message' => t('Import is starting.'),
            'finished' => '\Drupal\recurring_import\addImportContent::addImportContentItemCallback',
          );
          batch_set($batch);
      } else { return false; }

    } else { return false; }
  }

  // Read CSV and convert to array.
  public function csvtoarray($filename='', $delimiter){
    if(!file_exists($filename) || !is_readable($filename)) return FALSE;
    $header = NULL;
    $data = array();
    if (($handle = fopen($filename, 'r')) !== FALSE ) {
      while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE)
      {
        if(!$header){
          $header = $row;
        }else{
          $safe_row = Xss::filter($row); // prevent from (XSS) attacks
          $data[] = array_combine($header, $safe_row);
        }
      }
      fclose($handle);
    }
    return $data;
  }

}