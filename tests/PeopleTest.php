<?php

namespace Affinitybridge\NationBuilder\Tests;

use Affinitybridge\NationBuilder\Environment\Callback as CallbackEnvironment;
use Affinitybridge\NationBuilder\Container as Container;
use Affinitybridge\NationBuilder\Api as Api;

class PeopleTest extends \PHPUnit_Framework_TestCase
{
    const TEST_TAG = 'automated_api_test';
    const PERSON_A = [
        'email' => 'create@nationbuilder-api-test.affinitybridge.com',
        'first_name' => 'Test',
        'last_name' => 'Nationbuilder Api Tester',
        'tags' => [self::TEST_TAG],
        'home_address' => [
            'address1' => '207 West Hastings Street',
            'address2' => '',
            'address3' => '',
            'city' => 'Vancouver',
            'state' => 'BC',
            'zip' => 'V6B 1H7',
            'country_code' => 'CA',
            'lat' => 49.2828995,
            'lon' => -123.1118114,
        ],
    ];

    protected $credentials = [];
    protected $people = null;

    protected function setUp()
    {
        $this->loadCredentials();
        $this->buildSystem();
    }

    protected function loadCredentials()
    {
        $filename = dirname(dirname(__FILE__)) . '/.test_credentials.json';
        if (file_exists($filename)) {
            $this->credentials = json_decode(file_get_contents($filename), true);
        }
    }

    protected function buildSystem()
    {
        $dummyContainer = new Container();
        $env = new CallbackEnvironment($dummyContainer);
        $callbacks = new CallbacksHelper($this->credentials);

        $env->setCallback('credentialsLoad', [$callbacks, 'credentialsLoad']);
        $env->setCallback('httpCall', [$callbacks, 'httpCall']);

        $api = new Api(['environment' => $env]);
        $connection = $api->connect('dummyConnectionId');
        $this->people = $connection->people();
    }

    protected function clearTestData()
    {
        try {
            $matchResult = $this->people->match(['email' => static::PERSON_A['email']]);
        }
        catch (\Exception $e) {
            // Do nothing if we didn't find any test data.
        }

        if (isset($matchResult['person']['id'])) {
            try {
                $deleteResult = $this->people->delete($matchResult['person']['id']);
            }
            catch (\Exception $e) {
                // Do nothing if deletion failed.
            }
        }
    }

    public function assertPreConditions()
    {
        $this->assertArrayHasKey('api_base_url', $this->credentials);
        $this->assertArrayHasKey('access_token', $this->credentials);
    }

    public function testCreate()
    {
        $this->clearTestData();

        $result = $this->people->create(static::PERSON_A);

        $this->assertArrayHasKey('person', $result);
        $this->assertArrayHasKey('precinct', $result);
        $this->assertArrayHasKey('id', $result['person']);
        $this->assertArrayHasKey('email', $result['person']);
        $this->assertEquals(static::PERSON_A['email'], $result['person']['email']);

        return $result['person']['id'];
    }

    /**
     * @depends testCreate
     */
    public function testIndex($createdId)
    {
        $result = $this->people->index();

        $this->assertInternalType('array', $result, 'Retrieving the index of people should result in an array.');
        foreach ($result as $abbrPerson) {
            $this->assertArrayHasKey('id', $abbrPerson);
            $this->assertInternalType('integer', $abbrPerson['id']);
            $existingIds[] = $abbrPerson['id'];
        }
    }

    /**
     * @depends testCreate
     */
    public function testShow($createdId)
    {
        $result = $this->people->show($createdId);

        $this->assertArrayHasKey('person', $result);
        $this->assertArrayHasKey('precinct', $result);
        $this->assertInternalType('array', $result['person']);
        $this->assertArrayHasKey('id', $result['person']);
        $this->assertEquals($createdId, $result['person']['id']);
    }

    /**
     * @depends testCreate
     */
    public function testMatch($createdId)
    {
        $result = $this->people->match(['email' => static::PERSON_A['email']]);

        $this->assertArrayHasKey('person', $result);
        $this->assertArrayHasKey('precinct', $result);
        $this->assertInternalType('array', $result['person']);
        $this->assertArrayHasKey('id', $result['person']);
        $this->assertEquals($createdId, $result['person']['id']);
    }

    /**
     * @depends testCreate
     */
    public function testSearch($createdId)
    {
        $searchParams = [
            'first_name' => static::PERSON_A['first_name'],
            'last_name' => static::PERSON_A['last_name'],
        ];

        $result = $this->people->search($searchParams);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('next', $result);
        $this->assertArrayHasKey('prev', $result);
        $this->assertInternalType('array', $result['results']);
        $this->assertArrayHasKey(0, $result['results']);
        $this->assertInternalType('array', $result['results'][0]);
        $this->assertArrayHasKey('id', $result['results'][0]);
        $this->assertEquals($createdId, $result['results'][0]['id']);
    }

    /**
     * @depends testCreate
     */
    public function testNearby($createdId)
    {
        $this->markTestSkipped('NB does not save the lat and lon given in testCreate(). It insists on geocoding the address, which takes too long for this test to find anything "nearby".');

        $nearbyParams = [
            'latitude' => static::PERSON_A['home_address']['lat'],
            'longitude' => static::PERSON_A['home_address']['lon'],
        ];

        $result = $this->people->nearby($nearbyParams);

        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('next', $result);
        $this->assertArrayHasKey('prev', $result);
        $this->assertInternalType('array', $result['results']);

        $found = false;
        foreach ($result['results'] as $person) {
            $this->assertInternalType('array', $person);
            $this->assertArrayHasKey('id', $person);
            if ($createdId == $person['id']) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    /**
     * @depends testCreate
     */
    public function testRegister($createdId)
    {
        $result = $this->people->register($createdId);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
    }

    /**
     * @depends testCreate
     */
    public function testMe($createdId)
    {
        $result = $this->people->me();

        $this->assertArrayHasKey('person', $result);
        $this->assertArrayHasKey('precinct', $result);
        $this->assertArrayHasKey('id', $result['person']);
    }

    public function customAssertHasOnlyTheseTags($personId, array $tags)
    {
        $tags = array_values(array_unique($tags));

        $result = $this->people->taggings($personId);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('taggings', $result);
        $this->assertInternalType('array', $result['taggings']);

        $found = [];
        foreach ($result['taggings'] as $tagging) {
            $this->assertInternalType('array', $tagging);
            $this->assertArrayHasKey('person_id', $tagging);
            $this->assertEquals($personId, $tagging['person_id']);

            $this->assertArrayHasKey('tag', $tagging);
            $found[] = $tagging['tag'];
        }
        $found = array_values(array_unique($found));

        $this->assertEmpty(array_diff($found, $tags));
        $this->assertEmpty(array_diff($tags, $found));
    }

    /**
     * @depends testCreate
     */
    public function testTaggings($createdId)
    {
        $this->customAssertHasOnlyTheseTags($createdId, [static::TEST_TAG]);
    }

    /**
     * @depends testCreate
     */
    public function testAddTags($createdId)
    {
        $additionalTags = [
            static::TEST_TAG . '_added_1',
            static::TEST_TAG . '_added_2',
            static::TEST_TAG . '_added_3',
        ];
        $result = $this->people->addTags($createdId, $additionalTags);

        $this->customAssertHasOnlyTheseTags($createdId, array_merge([static::TEST_TAG], $additionalTags));

        return $additionalTags;
    }

    /**
     * @depends testCreate
     * @depends testAddTags
     */
    public function testDeleteTag($createdId, array $additionalTags)
    {
        $tagToDelete = array_pop($additionalTags);

        $result = $this->people->deleteTag($createdId, $tagToDelete);

        $this->customAssertHasOnlyTheseTags($createdId, array_merge([static::TEST_TAG], $additionalTags));

        return $additionalTags;
    }

    /**
     * @depends testCreate
     * @depends testDeleteTag
     */
    public function testDeleteTags($createdId, array $additionalTags)
    {
        $result = $this->people->deleteTags($createdId, $additionalTags);

        $this->customAssertHasOnlyTheseTags($createdId, [static::TEST_TAG]);
    }

    /**
     * @depends testCreate
     */
    public function testUpdate($createdId)
    {
        $updateData = [
            'first_name' => static::PERSON_A['first_name'], // Only here to satisfy current Validator implementation.
            'last_name' => static::PERSON_A['last_name'], // Only here to satisfy current Validator implementation.
            'occupation' => 'Updated Occupation.'
        ];
        $result = $this->people->update($createdId, $updateData);

        $this->assertArrayHasKey('person', $result);
        $this->assertArrayHasKey('precinct', $result);
        $this->assertInternalType('array', $result['person']);

        $this->assertArrayHasKey('id', $result['person']);
        $this->assertEquals($createdId, $result['person']['id']);

        $this->assertArrayHasKey('occupation', $result['person']);
        $this->assertEquals($updateData['occupation'], $result['person']['occupation']);
    }
}
