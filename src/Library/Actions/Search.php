<?php

namespace Amondar\Sextant\Library\Actions;

use Amondar\Sextant\Contracts\SextantActionContract;
use Amondar\Sextant\Library\SextantCore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Class Filter
 *
 * @version 1.0.0
 * @author  Sereda Oleg <zaxodu11@gmail.com>
 */
class Search extends SextantCore implements SextantActionContract
{

    /**
     * Условия фильтрации.
     *
     * @var Collection
     */
    public $searchConditions = null;

    /**
     * Поле в запросе для анлиза фильтрации.
     *
     * @var string
     */
    protected $searchRequestField;

    /**
     * Конструсктор класс сортировок.
     *
     * @param Builder $query
     * @param         $requestField
     * @param Request $request
     * @param array   $restrictions
     * @param array   $params
     */
    public function __construct(Builder $query, $requestField, Request $request = null, $restrictions = [], $params = [])
    {
        parent::__construct($query, $query->getModel(), $request, $restrictions);
        $this->searchRequestField = $requestField;
        $this->searchConditions = collect([]);
        $this->get();
    }

    /**
     * Set queries.
     *
     * @return mixed
     */
    public function set()
    {
        if ( ! $this->searchConditions->isEmpty()) {
            //create hard where query
            $this->query->where(function ($query) {
                foreach ($this->searchConditions->groupBy('relation') as $relation => $conditions) {
                    if ($relation) {
                        $this->setRelationOperation($conditions, $relation, $query);
                    } else {
                        $this->setOperation($conditions, null, $query);
                    }
                }
            });
        }
    }

    /**
     * Get filtration parameters.
     *
     * @return void
     */
    protected function get()
    {
        // Check request fields for search operation.
        if ($this->request && $this->request->has($this->searchRequestField)) {
            try {
                // Decode search json.
                $search = json_decode($this->request->input($this->searchRequestField));

                // Check main parameters.
                if ( ! empty($search->fields) && ! empty($search->query) ) {
                    // Detect search morphemes.
                    $words = $this->detectSearchTermsBySettings($search->query);

                    // Add fields search.
                    foreach ( explode('|', $search->fields) as $field ) {
                        [ $relation, $table_name, $field_name ] = $this->getFieldParametersWithExistsCheck($field);
                        $this->addCondition($relation, $table_name, $field_name, $words);
                    }
                }
            }catch(\Throwable $e){
                //
            }
        }
    }

    /**
     * Add condition to array.
     *
     * @param $relation
     * @param $tableName
     * @param $fieldName
     * @param $condition
     */
    protected function addCondition($relation, $tableName, $fieldName, $queryWords)
    {
        if ($tableName && $fieldName) {
            $this->searchConditions->push($this->getConditionParameters($relation, $tableName, $fieldName, [
                'operation' => 'search',
                'value'     => $queryWords,
            ]));
        }
    }

    /**
     * Return valid search terms based on search engine settings.
     *
     * @param string $query
     *
     * @return array
     */
    protected function detectSearchTermsBySettings(string $query)
    {
        $searchSettings = config('sextant.search');
        $searchTerms = [];

        switch ($searchSettings['type']) {
            case 'words':
                preg_match_all('/\w+/imu', $query, $queryWords);
                $searchTerms = $queryWords[0] ?? [];
                if(empty($searchTerms)){
                    $searchTerms[] = $query;
                }
                break;
            default: // strict
                $searchTerms[] = $query;
                break;
        }

        return $searchTerms;
    }
}