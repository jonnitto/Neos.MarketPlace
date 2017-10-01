<?php
namespace Neos\MarketPlace\Eel;

/*
 * This file is part of the Neos.MarketPlace package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\QueryInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Eel;
use Neos\Utility\Arrays;

/**
 * ElasticSearchQueryBuilder
 */
class ElasticSearchQueryBuilder extends Eel\ElasticSearchQueryBuilder
{
    /**
     * @var boolean
     */
    protected $hasFulltext = false;

    /**
     * @return QueryInterface
     */
    public function getRequest()
    {
        $request = parent::getRequest();
        $copiedRequest = clone $request;
        self::skipAbandonnedPackages($copiedRequest);
        if ($this->hasFulltext !== false) {
            self::enforceFunctionScoring($copiedRequest);
        }

        return $copiedRequest;
    }

    /**
     * @param string $searchWord
     * @return $this
     */
    public function fulltext($searchWord)
    {
        $searchWord = trim($searchWord);
        if ($searchWord === '') {
            return $this;
        }
        $this->hasFulltext = true;

        $this->request->setValueByPath('query.filtered.query.bool.must', '');
        $this->request->setValueByPath('query.filtered.query.bool.should', '');
        $this->request->setValueByPath('query.filtered.query.bool.minimum_should_match', 1);
//        $this->request->appendAtPath('query.filtered.query.bool.should', [
//            [
//                'query_string' => [
//                    'fields' => [
//                        'title^10',
//                        '__composerVendor^5',
//                        '__maintainers.name^5',
//                        '__maintainers.tag^8',
//                        'description^2',
//                        'lastVersion.keywords.name^10',
//                        'lastVersion.keywords.tag^12',
//                        '__fulltext.*'
//                    ],
//                    'query' => str_replace('/', '\\/', $searchWord),
//                    'default_operator' => 'AND',
//                    'use_dis_max' => true
//                ]
//            ]
//        ]);

        return $this;
    }

    /**
     * return void
     * @param QueryInterface $request
     */
    protected static function skipAbandonnedPackages(QueryInterface $request)
    {
        $request->appendAtPath('query.filtered.filter.bool.must_not', [
            'exists' => [
                'field' => 'abandoned'
            ]
        ]);
    }

    /**
     * @param QueryInterface $request
     * @return QueryInterface
     */
    protected static function enforceFunctionScoring(QueryInterface $request)
    {
        $request->setByPath('query',
            [
                'function_score' => [
                    'functions' => [
                        [
                            'filter' => [
                                'term' => [
                                    '__typeAndSupertypes' => 'Neos.MarketPlace:Vendor'
                                ],
                            ],
                            'weight' => 1.2
                        ],
                        [
                            'field_value_factor' => [
                                'field' => 'downloadDaily',
                                'factor' => 0.5,
                                'modifier' => 'sqrt',
                                'missing' => 1
                            ]
                        ],
                        [
                            'field_value_factor' => [
                                'field' => 'githubStargazers',
                                'factor' => 1,
                                'modifier' => 'sqrt',
                                'missing' => 1
                            ]
                        ],
                        [
                            'field_value_factor' => [
                                'field' => 'githubForks',
                                'factor' => 0.5,
                                'modifier' => 'sqrt',
                                'missing' => 1
                            ]
                        ],
                        [
                            'gauss' => [
                                'lastVersion.time' => [
                                    'scale' => '60d',
                                    'offset' => '5d',
                                    'decay' => 0.5
                                ]
                            ]
                        ]
                    ],
                    'score_mode' => 'avg',
                    'boost_mode' => 'multiply',
                    'query' => $request['query']
                ]
            ]);
    }

}
