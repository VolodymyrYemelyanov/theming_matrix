<?php

namespace Drupal\custom_csv_import;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use League\Csv\Reader;

/**
 * Class CSVBatchImport.
 *
 * @package Drupal\custom_csv_import
 */
class CSVBatchImport {

    use StringTranslationTrait;

    private $batch;
    private $fid;
    private $file;
    private $skip_first_line;
    private $delimiter;
    private $enclosure;
    private $firstWordAdded = 0;
    private $secondWordAdded = 0;

    public function __construct($fid, $skip_first_line = FALSE, $delimiter = ',',
                                $enclosure = ',', $batch_name = 'Custom CSV import') {
        $this->fid = $fid;
        $this->file = File::load($fid);
        $this->skip_first_line = $skip_first_line;
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->batch = [
            'title' => $batch_name,
            'finished' => [$this, 'finished'],
            'file' => drupal_get_path('module', 'custom_csv_import') . '/src/CSVBatchImport.php',
        ];
        $this->parseCSV();
    }

    public function parseCSV() {
        $path = \Drupal::service('file_system')
            ->realpath($this->file->getFileUri());
        $csv = Reader::createFromPath($path);
        $csv->setOffset(1);
        $rows = $csv->fetchAll();
        foreach ($rows as &$value) {
            $keys = [
                'category',
                'product_name',
                'url',
                'color',
                'price',
                'description'
            ];
            $value = array_combine($keys, $value);
        }
        $this->setOperation($rows);
    }

    public function setOperation($data = []) {
        $this->batch['operations'][] = [[$this, 'processItem'], [$data]];
    }

    public function setBatch() {
        batch_set($this->batch);
    }

    public function processBatch() {
        batch_process();
    }


    public function processItem($data = [], &$context) {
        if (empty($context['sandbox'])) {
            $context['sandbox'] = [
                'progress' => 0,
                'rows' => $data,
                'max' => count($data),
            ];
        }
//       /* $tt = $this;*/
        $rows_to_parse = array_slice($context['sandbox']['rows'], $context['sandbox']['progress'], 20);
        foreach ($rows_to_parse as $row) {
            if ($row['product_name'] != NULL) {
                //save image from url to folder images
                $image = file_get_contents($row['url']);
                //$image = file_get_contents('public://myimages/custom.png');
                $file = file_save_data($image, 'public://images/custom.png', FILE_EXISTS_RENAME);
                $action = rand(0, 2);

                $node = Node::create([
                    'type' => 'product',
                    'langcode' => 'en',
                    'uid' => 1,
                    'status' => 1,
                    'title' => $row['product_name'],
                    'field_photo' => [
                        'target_id' =>  $file->id(),
                    ],
                    'field_color' => $this->taxTerm($row['color'], 'en', 'colors'),
                    'field_category' => $this->taxTerm($row['category'], 'en', 'product_category'),
                    'field_price' => $row['price'],
                    'body' => [
                        'value' => $this->makeBody($row['description'], $action, 'en'),
                        'format' => 'full_html',
                    ],
                    'field_name' => $row['product_name'],
                ]);
                $node->save();

                $transl_tax_col = [
                    'Aqua' => 'Морська хвиля',
                    'Aqua-Blue' => 'Морська хвиля світла',
                    'Aqua-Dark' => 'Морська хвиля темна',
                    'Black' => 'Чорний',
                    'Blue' => 'Голубий',
                    'Brown' => 'Коричневий',
                    'Green' => 'Зелений',
                    'Grey' => 'Сірий',
                    'Orange' => 'Оранжевий',
                    'Red' => 'Червоний',
                    'Violet' => 'Фіолетовий',
                    'Yellow' => 'Жовтий',
                ];
                $color = isset($transl_tax_col[$row['color']]) ? $transl_tax_col[$row['color']] : '';

                $transl_tax_cat = [
                    'Hats' => 'Шапки',
                    'Glasses' => 'Окуляри',
                    'Jumpers' => 'Джемпери',
                    'T-shirts' => 'Футболки',
                    'Shorts' => 'Шорти',
                    'Pants' => 'Штани',
                    'Sneakers' => 'Кросівки',
                    'Sandals' => 'Сандалі',
                ];
                $category = isset($transl_tax_cat[$row['category']]) ? $transl_tax_cat[$row['category']] : '';
                xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
                $node_es = $node->addTranslation('uk');
                $node_es->title = $row['product_name'];
                $node_es->body->value = $this->makeBody($row['description'], $action, 'uk');
                $node_es->field_color = $this->taxTerm($color,'uk', 'colors');
                $node_es->field_category = $this->taxTerm($category, 'uk', 'product_category');
                $node_es->field_price = $row['price'];
                $node_es->field_name = $row['product_name'];
                /*$node_es->set('field_photo', [
                    'target_id' =>  $file->id(),
                ]);*/
                $node_es->save();
                $context['sandbox']['progress']++;
                $context['results'][] = $node->id() . ' : ' . $node->label();
                $context['message'] = $this->t('Processed %count of %max', [
                    '%count' => $context['sandbox']['progress'],
                    '%max' => $context['sandbox']['max']
                ]);
                $xhprof_data = xhprof_disable();
                include_once "/var/www/xhprof-0.9.2/xhprof_lib/utils/xhprof_lib.php";
                include_once "/var/www/xhprof-0.9.2/xhprof_lib/utils/xhprof_runs.php";
                $xhprof_runs = new XHProfRuns_Default();
                $run_id = $xhprof_runs->save_run($xhprof_data, "test");
                // /xhprof
                if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
                    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
                }
            }
        }


    }

    public function taxTerm($data, $lang, $voc) {
        if ($data != NULL) {
            $query = \Drupal::entityQuery('taxonomy_term')
                ->condition('vid', $voc, NULL, $lang)
                ->condition('name', $data)
                ->range(0, 1);
            $result = $query->execute();
            $tid = reset($result);
            return ['target_id' => $tid];
        }
    }

    public function makeBody($body, $action, $lang) {
        $keyPhrases = ($lang == 'en') ? ['wysmienity', 'redukcja redundancji'] : ['вишуканий', 'змешення надмірності'];
        if ($this->firstWordAdded < 100 && $this->secondWordAdded < 100) {
            switch ($action) {
                case 1:
                    $this->firstWordAdded++;
                    return ($body . " " . $keyPhrases[0]);

                case 2:
                    $this->secondWordAdded++;
                    return ($body . " " . $keyPhrases[1]);

                default:
                    return $body;
            }
        }
        elseif ($this->firstWordAdded < 100) {
            $action = rand(0, 1);
            switch ($action) {
                case 1:
                    $this->firstWordAdded++;
                    return ($body . " " . $keyPhrases[0]);

                default:
                    return $body;
            }
        }
        elseif ($this->secondWordAdded < 100) {
            $action = rand(0, 1);
            switch ($action) {
                case 1:
                    $this->secondWordAdded++;
                    return ($body . " " . $keyPhrases[1]);

                default:
                    return $body;
            }
        }
        else {
            return $body;
        }
    }

    public function finished($success, $result) {
        if ($success) {
            $message = \Drupal::translation()
                ->formatPlural(count($result), 'One post processed.',
                    '@count posts processed');
        } else {
            $message = t('Finished with an error.');
        }
        drupal_set_message($message);
    }
}


