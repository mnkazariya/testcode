<?php
namespace Drupal\recurring_import;

use Drupal\file\Entity\File;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Messenger\MessengerInterface;

class addImportContent {
  public static function addImportContentItem($item, &$context){
    $context['sandbox']['current_item'] = $item;
    $message = 'Updating...  ' . $item['Name'];
    $results = array();
    $processedData = update_node($item);
    $context['message'] = $message;
    $context['results'][] = $processedData;
  }
  function addImportContentItemCallback($success, $results, $operations) {

    // fetch school numbers which is not updated due to data not exist for that particular school number.
    foreach ($results as $key => $value) {
      foreach ($value as $keys => $values) {
        if(empty($values)){
          $notexists[] = $keys;
        }else{
          $exists[] = $keys;
        }
      }
    }
    if(!empty($exists)){
      $count_processed = count($exists);
    }
    

    if(!empty($notexists)){
      $countNot_processed = count($notexists);
      $notprocessed = implode(', ', $notexists);
      $notupdatedData = " the school numbers ( <strong>".$notprocessed. "</strong> ) not exist in database.";
    } else {
      $notupdatedData = "";
    }


    // success message
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        $count_processed,
        'One item processed.', '@count school data updated.'
      );
      
      if($notupdatedData != ""){
        $message1 = \Drupal::translation()->formatPlural(
          $countNot_processed,
          'One item not processed.', '@count school data fails to update because '.$notupdatedData
        );
      }
    }
    else {
      $message = t('Finished with an error.');
    }

    \Drupal::messenger()->addMessage($message);
      
    if(!empty($message1)){
      \Drupal::messenger()->addMessage($message1);
    }
  }
}

  // update node process.  
  function update_node($item) {

    if(!empty($item)){
      if($item['Number'] != ''){

        $connection = Database::getConnection();
        $results = getschoolData($connection,$item['Number']);

        if(!empty($results)) {
          $attribute_id = [];
          foreach ($results as $row) {
            $attribute_id[] = $row->entity_id;
          }
          
          if(!empty($attribute_id)) {

            $node_ids = get_node_id($connection,$attribute_id);
            $nid = $node_ids[0];

            if($nid != ''){
              $node = Node::load($nid);
              $school_attr_field = $node->get('field_school_attributes')->getValue();

              if(!empty($school_attr_field)) {
                $updt = 0;
                foreach ( $school_attr_field as $element ) {
                    
                    $p = \Drupal\paragraphs\Entity\Paragraph::load( $element['target_id'] );
                    
                    // CSV rows as below will be updates

                    if(!empty($item['Enrollment'])){
                      $p->set('field_total_enrollment',$item['Enrollment']);
                      $updt++;
                    }

                    if(!empty($item['State star rating'])){
                      $termID = "";
                      $ratingTerm = taxonomy_term_load_multiple_by_name($item['State star rating'],'star_rating');
                      if(!empty($ratingTerm)) {
                        foreach ($ratingTerm as $keys => $values) { 
                          $termID = $values->get('tid')->value;
                        }
                        if($termID != ''){
                          $p->set('field_md_5_star_school_rating',$termID);
                          $updt++;
                        }
                      }
                    }

                    if(!empty($item['Graduation rate'])){
                      $p->set('field_graduation_rate',$item['Graduation rate']);
                      $updt++;
                    }
                    
                    if(!empty($item['SAT: Average schoolwide composite'])) {
                      $p->set('field_sat_average_schoolwide_com',$item['SAT: Average schoolwide composite']);
                      $updt++;
                    }

                    if(!empty($item['Link: School budget'])){
                      $p->set('field_school_budget',$item['Link: School budget']);
                      $updt++;
                    }

                    if(!empty($item['Link: School data profile'])){
                      $p->set('field_link_school_profile',$item['Link: School data profile']);
                      $updt++;
                    }

                    if(!empty($item['Link: School survey results'])){
                      $p->set('field_link_school_survey_results',$item['Link: School survey results']);
                      $updt++;
                    }

                    if(!empty($item['Link: School performance plan'])){
                      $p->set('field_link_school_performance',$item['Link: School performance plan']);
                      $updt++;
                    }

                    if(!empty($item['Link: School effectiveness review'])){
                      $p->set('field_link_school_effectivness',$item['Link: School effectiveness review']);
                      $updt++;
                    }

                    if(!empty($item['Link: School renewal report'])){
                      $p->set('field_link_school_renewal_report',$item['Link: School renewal report']);
                      $updt++;
                    }

                    if(!empty($item['Link: MSDE school report card'])){
                      $p->set('field_link_msde_school_report_ca',$item['Link: MSDE school report card']);
                      $updt++;
                    }
                }

                if($updt > 0) {
                 
                    //save paragraph data
                    $p->save(); 

                    //node update with paragraph values
                    $node->field_content = array(
                      array(
                        'target_id' => $school_attr_field[0]['target_id'],
                        'target_revision_id' => $school_attr_field[0]['target_revision_id'],
                      )
                    );

                    // node save 
                    $node->save();
                }

              }
            }
          }
        }

        if(!empty($nid)){
          $total_updated[$item['Number']] = $nid;
        } 
        else 
        { 
          $total_updated[$item['Number']] = ""; 
        }

      }
    }
  
    return $total_updated;
  } 
 
  // get node id from paragraph entity id.
  function get_node_id($connection,$attribute_id) {
    $node_id = [];
    for( $i=0;$i<count($attribute_id);$i++ ) {
      $node_query = $connection->select('node__field_school_attributes', 'n')
        ->fields('n', array('entity_id'))
        ->condition('n.field_school_attributes_target_id', $attribute_id[$i], '=');
      $node_data = $node_query->execute();
      $node_results = $node_data->fetchAll(\PDO::FETCH_OBJ);
      $node_id[] = $node_results[0]->entity_id;
    }
    return $node_id;
  }

  // get school's data from school number.
  function getschoolData($connection,$schoolNumber){
    $query = $connection->select('paragraph__field_school_id', 's')
        ->fields('s', array('entity_id'))
        ->condition('s.field_school_id_value', $schoolNumber, '=');
    $data = $query->execute();
    $results = $data->fetchAll(\PDO::FETCH_OBJ);
    return $results;
  }

  // term load by term name
  function taxonomy_term_load_multiple_by_name($name, $vocabulary = NULL) {
    $values = array('name' => trim($name));
    if (isset($vocabulary)) {
      $vocabularies = taxonomy_vocabulary_get_names();
      if (isset($vocabularies[$vocabulary])) {
        $values['vid'] = $vocabulary;
      }
      else {
        return array();
      }
    }
    // return entity_load_multiple_by_properties('taxonomy_term', $values);
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree($values['vid']);
    return $terms;
  }
