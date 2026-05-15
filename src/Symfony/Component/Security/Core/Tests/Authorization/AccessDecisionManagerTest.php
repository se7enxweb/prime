<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authorization;


use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class AccessDecisionManagerTest extends TestCase
{
    #[Group('legacy')]    public function testSupportsClass()
    {
        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsClass(true),
            $this->getVoterSupportsClass(false),
        ));
        $this->assertTrue($manager->supportsClass('FooClass'));

        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsClass(false),
            $this->getVoterSupportsClass(false),
        ));
        $this->assertFalse($manager->supportsClass('FooClass'));
    }

    #[Group('legacy')]    public function testSupportsAttribute()
    {
        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsAttribute(true),
            $this->getVoterSupportsAttribute(false),
        ));
        $this->assertTrue($manager->supportsAttribute('foo'));

        $manager = new AccessDecisionManager(array(
            $this->getVoterSupportsAttribute(false),
            $this->getVoterSupportsAttribute(false),
        ));
        $this->assertFalse($manager->supportsAttribute('foo'));
    }

    /**
     */
    public function testSetUnsupportedStrategy()
    {
        $this->expectException(\InvalidArgumentException::class);

        new AccessDecisionManager(array($this->getVoter(VoterInterface::ACCESS_GRANTED)), 'fooBar');
    }

    #[DataProvider('getStrategyTests')]    public function testStrategies($strategy, $voters, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions, $expected)
    {
        $token = $this->getMockBuilder('Symfony\Component\Security\Core\Authentication\Token\TokenInterface')->getMock();
        $manager = new AccessDecisionManager($voters, $strategy, $allowIfAllAbstainDecisions, $allowIfEqualGrantedDeniedDecisions);

        $this->assertSame($expected, $manager->decide($token, array('ROLE_FOO')));
    }

    #[DataProvider('getStrategiesWith2RolesTests')]    public function testStrategiesWith2Roles($token, $strategy, $voter, $expected)
    {
        $manager = new AccessDecisionManager(array($voter), $strategy);

        $this->assertSame($expected, $manager->decide($token, array('ROLE_FOO', 'ROLE_BAR')));
    }

    public static function getStrategiesWith2RolesTests()
    {
        $token = new class() implements TokenInterface {
            public function __toString() { return ''; }
            public function getRoles() { return []; }
            public function getCredentials() { return null; }
            public function getUser() { return null; }
            public function setUser($user) {}
            public function getUsername() { return ''; }
            public function isAuthenticated() { return false; }
            public function setAuthenticated($isAuthenticated) {}
            public function eraseCredentials() {}
            public function getAttributes() { return []; }
            public function setAttributes(array $attributes) {}
            public function hasAttribute($name) { return false; }
            public function getAttribute($name) { return null; }
            public function setAttribute($name, $value) {}
            public function serialize() { return ''; }
            public function unserialize($serialized) {}
            public function __serialize(): array { return []; }
            public function __unserialize(array $data): void {}
        };

        return array(
            array($token, 'affirmative', self::getVoter(VoterInterface::ACCESS_DENIED), false),
            array($token, 'affirmative', self::getVoter(VoterInterface::ACCESS_GRANTED), true),

            array($token, 'consensus', self::getVoter(VoterInterface::ACCESS_DENIED), false),
            array($token, 'consensus', self::getVoter(VoterInterface::ACCESS_GRANTED), true),

            array($token, 'unanimous', self::getVoterFor2Roles($token, VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_DENIED), false),
            array($token, 'unanimous', self::getVoterFor2Roles($token, VoterInterface::ACCESS_DENIED, VoterInterface::ACCESS_GRANTED), false),
            array($token, 'unanimous', self::getVoterFor2Roles($token, VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_DENIED), false),
            array($token, 'unanimous', self::getVoterFor2Roles($token, VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_GRANTED), true),
        );
    }

    protected static function getVoterFor2Roles($token, $vote1, $vote2)
    {
        $map = array(
            serialize(array('ROLE_FOO')) => $vote1,
            serialize(array('ROLE_BAR')) => $vote2,
        );

        return new class($map) implements VoterInterface {
            private $map;
            public function __construct(array $map) { $this->map = $map; }
            public function supportsAttribute($attribute) { return true; }
            public function supportsClass($class) { return true; }
            public function vote(TokenInterface $token, $object, array $attributes) {
                $key = serialize($attributes);
                return isset($this->map[$key]) ? $this->map[$key] : VoterInterface::ACCESS_ABSTAIN;
            }
        };
    }

    public static function getStrategyTests()
    {
        return array(
            // affirmative
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(1, 0, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(1, 2, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(0, 1, 0), false, true, false),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(0, 0, 1), false, true, false),
            array(AccessDecisionManager::STRATEGY_AFFIRMATIVE, self::getVoters(0, 0, 1), true, true, true),

            // consensus
            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(1, 0, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(1, 2, 0), false, true, false),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 1, 0), false, true, true),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(0, 0, 1), false, true, false),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(0, 0, 1), true, true, true),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 1), false, true, true),

            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 0), false, false, false),
            array(AccessDecisionManager::STRATEGY_CONSENSUS, self::getVoters(2, 2, 1), false, false, false),

            // unanimous
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(1, 0, 0), false, true, true),
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(1, 0, 1), false, true, true),
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(1, 1, 0), false, true, false),

            array(AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(0, 0, 2), false, true, false),
            array(AccessDecisionManager::STRATEGY_UNANIMOUS, self::getVoters(0, 0, 2), true, true, true),
        );
    }

    protected static function getVoters($grants, $denies, $abstains)
    {
        $voters = array();
        for ($i = 0; $i < $grants; ++$i) {
            $voters[] = self::getVoter(VoterInterface::ACCESS_GRANTED);
        }
        for ($i = 0; $i < $denies; ++$i) {
            $voters[] = self::getVoter(VoterInterface::ACCESS_DENIED);
        }
        for ($i = 0; $i < $abstains; ++$i) {
            $voters[] = self::getVoter(VoterInterface::ACCESS_ABSTAIN);
        }

        return $voters;
    }

    protected static function getVoter($vote)
    {
        return new class($vote) implements VoterInterface {
            private $vote;
            public function __construct(int $vote) { $this->vote = $vote; }
            public function supportsAttribute($attribute) { return true; }
            public function supportsClass($class) { return true; }
            public function vote(TokenInterface $token, $object, array $attributes) { return $this->vote; }
        };
    }

    protected function getVoterSupportsClass($ret)
    {
        $voter = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface')->getMock();
        $voter->expects($this->any())
              ->method('supportsClass')
              ->willReturn($ret);

        return $voter;
    }

    protected function getVoterSupportsAttribute($ret)
    {
        $voter = $this->getMockBuilder('Symfony\Component\Security\Core\Authorization\Voter\VoterInterface')->getMock();
        $voter->expects($this->any())
              ->method('supportsAttribute')
              ->willReturn($ret);

        return $voter;
    }
}
