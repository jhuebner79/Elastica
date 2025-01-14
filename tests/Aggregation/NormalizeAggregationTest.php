<?php

namespace Elastica\Test\Aggregation;

use Elastica\Aggregation\DateHistogram;
use Elastica\Aggregation\NormalizeAggregation;
use Elastica\Aggregation\Sum;
use Elastica\Document;
use Elastica\Exception\InvalidException;
use Elastica\Index;
use Elastica\Query;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;

/**
 * @internal
 */
class NormalizeAggregationTest extends BaseAggregationTest
{
    use ExpectDeprecationTrait;

    /**
     * @group unit
     */
    public function testToArray(): void
    {
        $expected = [
            'date_histogram' => [
                'field' => 'date',
                'calendar_interval' => 'day',
            ],
            'aggs' => [
                'sum_agg' => [
                    'sum' => [
                        'field' => 'value',
                    ],
                ],
                'normalize_agg' => [
                    'normalize' => [
                        'buckets_path' => 'sum_agg',
                        'method' => 'percent_of_sum',
                        'format' => '00.00%',
                    ],
                ],
            ],
        ];

        $sumAgg = (new Sum('sum_agg'))
            ->setField('value')
        ;

        $normalizeAgg = (new NormalizeAggregation('normalize_agg', 'sum_agg', 'percent_of_sum'))
            ->setFormat('00.00%')
        ;

        $dateHistogramAgg = (new DateHistogram('histogram_agg', 'date', 'day'))
            ->addAggregation($sumAgg)
            ->addAggregation($normalizeAgg)
        ;

        $this->assertEquals($expected, $dateHistogramAgg->toArray());
    }

    /**
     * @group unit
     * @group legacy
     */
    public function testLegacyConstructWithNoBucketsPathAndNoMethod(): void
    {
        $this->expectDeprecation('Since ruflin/elastica 7.1.3: Not passing a 2nd argument to "Elastica\Aggregation\NormalizeAggregation::__construct()" is deprecated, pass a string instead. It will be removed in 8.0.');
        $this->expectDeprecation('Since ruflin/elastica 7.1.3: Not passing a 3rd argument to "Elastica\Aggregation\NormalizeAggregation::__construct()" is deprecated, pass a string instead. It will be removed in 8.0.');

        new NormalizeAggregation('normalize_agg');
    }

    /**
     * @group unit
     * @group legacy
     */
    public function testLegacyConstructWithNullBucketsPath(): void
    {
        $this->expectDeprecation('Since ruflin/elastica 7.1.3: Passing null as 2nd argument to "Elastica\Aggregation\NormalizeAggregation::__construct()" is deprecated, pass a string instead. It will be removed in 8.0.');

        new NormalizeAggregation('normalize_agg', null, 'method');
    }

    /**
     * @group unit
     * @group legacy
     */
    public function testLegacyConstructWithNullMethod(): void
    {
        $this->expectDeprecation('Since ruflin/elastica 7.1.3: Passing null as 3rd argument to "Elastica\Aggregation\NormalizeAggregation::__construct()" is deprecated, pass a string instead. It will be removed in 8.0.');

        new NormalizeAggregation('normalize_agg', 'buckets_path', null);
    }

    /**
     * @group unit
     * @group legacy
     */
    public function testLegacyToArrayWithNoBucketsPath(): void
    {
        $this->expectException(InvalidException::class);
        $this->expectExceptionMessage('Buckets path is required');

        (new NormalizeAggregation('normalize_agg'))->toArray();
    }

    /**
     * @group unit
     * @group legacy
     */
    public function testToArrayInvalidMethod(): void
    {
        $this->expectException(InvalidException::class);
        $this->expectExceptionMessage('Method parameter is required');

        (new NormalizeAggregation('normalize_agg', 'buckets_path'))->toArray();
    }

    /**
     * @group functional
     */
    public function testNormalizeAggregation(): void
    {
        $this->_checkVersion('7.9');

        $index = $this->getIndexForTest();

        $dateHistogramAgg = new DateHistogram('histogram_agg', 'date', 'day');
        $dateHistogramAgg->setFormat('yyyy-MM-dd');

        $sumAgg = new Sum('sum_agg');
        $sumAgg->setField('value');
        $dateHistogramAgg->addAggregation($sumAgg);

        $normalizeAgg = new NormalizeAggregation('normalize_agg', 'sum_agg', 'percent_of_sum');
        $normalizeAgg->setFormat('00.00%');
        $dateHistogramAgg->addAggregation($normalizeAgg);

        $query = new Query();
        $query->addAggregation($dateHistogramAgg);

        $dateHistogramAggResult = $index->search($query)->getAggregation('histogram_agg')['buckets'];

        $this->assertCount(3, $dateHistogramAggResult);

        $this->assertEquals('14.29%', $dateHistogramAggResult[0]['normalize_agg']['value_as_string']);
        $this->assertEquals('57.14%', $dateHistogramAggResult[1]['normalize_agg']['value_as_string']);
        $this->assertEquals('28.57%', $dateHistogramAggResult[2]['normalize_agg']['value_as_string']);
    }

    private function getIndexForTest(): Index
    {
        $index = $this->_createIndex();

        $index->addDocuments([
            new Document('1', ['date' => '2018-12-01T01:00:00', 'value' => 1]),
            new Document('2', ['date' => '2018-12-01T10:00:00', 'value' => 2]),
            new Document('3', ['date' => '2018-12-02T02:00:00', 'value' => 3]),
            new Document('4', ['date' => '2018-12-02T15:00:00', 'value' => 4]),
            new Document('5', ['date' => '2018-12-02T20:00:00', 'value' => 5]),
            new Document('6', ['date' => '2018-12-03T03:00:00', 'value' => 6]),
        ], ['refresh' => 'true']);

        return $index;
    }
}
