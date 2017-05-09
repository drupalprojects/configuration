<?php

namespace Configuration\Processors;

use Configuration\Configuration;

class VocabularyProcessor extends AbstractProcessor {

  static public function availableProcessors() {
    return array(
       // Converts the keys of an array from Vocabulary Ids to Vocabulary machine names.
      'VocabularyKeyId2Name',

       // Converts the values of an array from Vocabulary Ids to Vocabulary machine names.
      'VocabularyValueId2Name',

       // Converts the keys and the values of an array from Vocabulary Ids to Vocabulary machine names.
      'VocabularyAllId2Name',
    );
  }

  public function apply(Configuration $configuration, $properties = array()) {
    $vocabulary_names = array();
    $vocabularies = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_names[$vocabulary->vid] = $vocabulary->name;
    }

    $converted = array();
    foreach ($properties as $property) {
      $array = $configuration->getValue($property);

      foreach ($array as $key => $value) {
        switch ($this->getName()) {
          case 'VocabularyKeyId2Name':
            $converted[$Vocabulary_names[$key]] = $value;
            break;

          case 'VocabularyValueId2Name':
            $converted[$key] = $Vocabulary_names[$value];
            break;

          case 'VocabularyAllId2Name':
            $converted[$Vocabulary_names[$key]] = $Vocabulary_names[$value];
            break;
        }
      }
      $configuration->setValue($property, $converted);
    }
  }

  public function revert(Configuration $configuration, $properties = array()) {
    $vocabulary_vids = array();
    $vocabularies = taxonomy_get_vocabularies();
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_vids[$vocabulary->machine_name] = $vocabulary->vid;
    }

    $converted = array();
    foreach ($properties as $property) {
      $array = $configuration->getValue($property);

      foreach ($array as $key => $value) {
        switch ($this->getName()) {
          case 'VocabularyKeyId2Name':
            $converted[$Vocabularys_ids[$key]] = $value;
            break;

          case 'VocabularyValueId2Name':
            $converted[$key] = $Vocabularys_ids[$value];
            break;

          case 'VocabularyAllId2Name':
            $converted[$Vocabularys_ids[$key]] = $Vocabularys_ids[$value];
            break;
        }
      }
      $configuration->setValue($property, $converted);
    }
  }
}
