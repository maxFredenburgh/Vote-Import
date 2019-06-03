<?php

namespace Drupal\voteexport\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;

/**
 * Class DefaultForm.
 */
class DefaultForm extends FormBase {


    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'voteExportForm';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {

        // generate a list of content types
        $all_content_types = NodeType::loadMultiple();
        $content_types = array();
        foreach ($all_content_types as $machine_name => $content_type) {
            $content_types[$content_type->id()] = $content_type->label();
        }
        ksort($content_types);


        // create a simple multi select list of content types
        $form['contenttype'] = [
            '#type' => 'select',
            '#title' => $this->t('Select a content type to merge'),
            '#options' => $content_types,
            '#size' => count($content_types),
            '#weight' => '0',
        ];
        if ($form_state->isSubmitted()) {
            // get selected content type
            $content_type = $form_state->getValue('contenttype');
            if (empty($content_type)) {
                drupal_set_message("No content type selected for mapping", 'warning');
            }
            else{
                $query = \Drupal::entityQuery('node');
                $query->condition('type',$content_type );
                $nids = $query->execute();
                $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);


                $rows = array();

                if($content_type=='app'){
                    $connection = \Drupal::database();
                    $query = $connection->query("SELECT * FROM {node__fivestar_votes_temp} ORDER BY nid");
                    $result = $query->fetchAll();

                    foreach ($nodes as $node) {
                        $count=0;
                        foreach($result as $res){
                            if($node->get('field_previous_id')->value == $res->nid){
                                $count+=1;
                                if($count<2){
                                    $rows[] = array(
                                        $node->get('title')->value,
                                        $node->get('nid')->value,
                                        $node->get('field_previous_id')->value,
                                        $res->nid,
                                        $res->value,
                                        $res->count,
                                    );
                                }
                            }

                        }
                    }

                }else{
                    $connection = \Drupal::database();
                    $query = $connection->query("SELECT * FROM {node__votes_temp}");
                    $result = $query->fetchAll();

                    foreach ($nodes as $node) {
                        foreach($result as $res){
                            if($node->get('field_previous_id')->value == $res->entity_id){
                                $rows[] = array(
                                    $node->get('title')->value,
                                    $node->get('nid')->value,
                                    $node->get('field_previous_id')->value,
                                    $res->entity_id,
                                    $res->field_dataset_vote_value,
                                );
                            }

                        }
                    }
                }

                $form['mapping'] = [
                    '#type' => 'table',
                    '#header' => [$this->t('Title'), $this->t('nid'), $this->t('prevnid'), $this->t('match'), $this->t('Vote'), $this->t(' of Count (if applicable')],
                    '#rows' => $rows,
                ];

            }
        }

        $form['view_mapping'] = array(
            '#name' => 'view_mappings',
            '#type' => 'submit',
            '#value' => t('View Mapping'),
            '#submit' => array([$this, 'viewMappings']),
        );

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Update Votes'),
        ];
        return $form;
    }

    public function viewMappings(array &$form, FormStateInterface &$form_state) {
        $form_state->setRebuild();
    }

    /**
     * {@inheritdoc}
     */
    public function validateForm(array &$form, FormStateInterface $form_state) {
        parent::validateForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $content_type = $form_state->getValue('contenttype');

        $query = \Drupal::entityQuery('node');
        $query->condition('type', $content_type);
        //$query->condition('nid', '600');
        $nids = $query->execute();
        $nodes = \Drupal\node\Entity\Node::loadMultiple($nids);

        if($content_type=='app'){
            $connection = \Drupal::database();
            $query = $connection->query("SELECT * FROM {node__fivestar_votes_temp} ORDER BY nid");
            $result = $query->fetchAll();

            foreach ($nodes as $node) {
                $count=0;
                foreach($result as $res){
                    if($node->get('field_previous_id')->value == $res->nid){
                        $count+=1;
                        if($count<2){
                            $node->field_fivestar_vote_count=$res->count;
                            $node->field_fivestar_vote_value=$res->value;
                            $node->save();
                        }
                    }

                }
            }

        }else{
            $connection = \Drupal::database();
            $query = $connection->query("SELECT * FROM {node__votes_temp}");
            $result = $query->fetchAll();

            foreach ($nodes as $node) {
                foreach($result as $res){
                    if($node->get('field_previous_id')->value == $res->entity_id){
                        $node->field_vote_value=$res->field_dataset_vote_value;
                        $node->save();
                    }

                }
            }
        }

    }
}
