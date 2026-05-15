<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;


use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IpValidator;
use Symfony\Component\Validator\Validation;

class IpValidatorTest extends AbstractConstraintValidatorTest
{
    protected function getApiVersion()
    {
        return Validation::API_VERSION_2_5;
    }

    protected function createValidator()
    {
        return new IpValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new Ip());

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new Ip());

        $this->assertNoViolation();
    }

    /**
     */
    public function testExpectsStringCompatibleType()
    {
        $this->expectException(\Symfony\Component\Validator\Exception\UnexpectedTypeException::class);

        $this->validator->validate(new \stdClass(), new Ip());
    }

    /**
     */
    public function testInvalidValidatorVersion()
    {
        $this->expectException(\Symfony\Component\Validator\Exception\ConstraintDefinitionException::class);

        new Ip(array(
            'version' => 666,
        ));
    }

    #[DataProvider('getValidIpsV4')]    public function testValidIpsV4($ip)
    {
        $this->validator->validate($ip, new Ip(array(
            'version' => Ip::V4,
        )));

        $this->assertNoViolation();
    }

    public static function getValidIpsV4()
    {
        return array(
            array('0.0.0.0'),
            array('10.0.0.0'),
            array('123.45.67.178'),
            array('172.16.0.0'),
            array('192.168.1.0'),
            array('224.0.0.1'),
            array('255.255.255.255'),
            array('127.0.0.0'),
        );
    }

    #[DataProvider('getValidIpsV6')]    public function testValidIpsV6($ip)
    {
        $this->validator->validate($ip, new Ip(array(
            'version' => Ip::V6,
        )));

        $this->assertNoViolation();
    }

    public static function getValidIpsV6()
    {
        return array(
            array('2001:0db8:85a3:0000:0000:8a2e:0370:7334'),
            array('2001:0DB8:85A3:0000:0000:8A2E:0370:7334'),
            array('2001:0Db8:85a3:0000:0000:8A2e:0370:7334'),
            array('fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334'),
            array('fe80:0000:0000:0000:0202:b3ff:fe1e:8329'),
            array('fe80:0:0:0:202:b3ff:fe1e:8329'),
            array('fe80::202:b3ff:fe1e:8329'),
            array('0:0:0:0:0:0:0:0'),
            array('::'),
            array('0::'),
            array('::0'),
            array('0::0'),
            // IPv4 mapped to IPv6
            array('2001:0db8:85a3:0000:0000:8a2e:0.0.0.0'),
            array('::0.0.0.0'),
            array('::255.255.255.255'),
            array('::123.45.67.178'),
        );
    }

    #[DataProvider('getValidIpsAll')]    public function testValidIpsAll($ip)
    {
        $this->validator->validate($ip, new Ip(array(
            'version' => Ip::ALL,
        )));

        $this->assertNoViolation();
    }

    public static function getValidIpsAll()
    {
        return array_merge(self::getValidIpsV4(), self::getValidIpsV6());
    }

    #[DataProvider('getInvalidIpsV4')]    public function testInvalidIpsV4($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V4,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidIpsV4()
    {
        return array(
            array('0'),
            array('0.0'),
            array('0.0.0'),
            array('256.0.0.0'),
            array('0.256.0.0'),
            array('0.0.256.0'),
            array('0.0.0.256'),
            array('-1.0.0.0'),
            array('foobar'),
        );
    }

    #[DataProvider('getInvalidPrivateIpsV4')]    public function testInvalidPrivateIpsV4($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V4_NO_PRIV,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPrivateIpsV4()
    {
        return array(
            array('10.0.0.0'),
            array('172.16.0.0'),
            array('192.168.1.0'),
        );
    }

    #[DataProvider('getInvalidReservedIpsV4')]    public function testInvalidReservedIpsV4($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V4_NO_RES,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidReservedIpsV4()
    {
        return array(
            array('0.0.0.0'),
            array('240.0.0.1'),
            array('255.255.255.255'),
        );
    }

    #[DataProvider('getInvalidPublicIpsV4')]    public function testInvalidPublicIpsV4($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V4_ONLY_PUBLIC,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPublicIpsV4()
    {
        return array_merge(self::getInvalidPrivateIpsV4(), self::getInvalidReservedIpsV4());
    }

    #[DataProvider('getInvalidIpsV6')]    public function testInvalidIpsV6($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V6,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidIpsV6()
    {
        return array(
            array('z001:0db8:85a3:0000:0000:8a2e:0370:7334'),
            array('fe80'),
            array('fe80:8329'),
            array('fe80:::202:b3ff:fe1e:8329'),
            array('fe80::202:b3ff::fe1e:8329'),
            // IPv4 mapped to IPv6
            array('2001:0db8:85a3:0000:0000:8a2e:0370:0.0.0.0'),
            array('::0.0'),
            array('::0.0.0'),
            array('::256.0.0.0'),
            array('::0.256.0.0'),
            array('::0.0.256.0'),
            array('::0.0.0.256'),
        );
    }

    #[DataProvider('getInvalidPrivateIpsV6')]    public function testInvalidPrivateIpsV6($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V6_NO_PRIV,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPrivateIpsV6()
    {
        return array(
            array('fdfe:dcba:9876:ffff:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:fdc6:c46b:bb8f:7d4c'),
            array('fdc6:c46b:bb8f:7d4c:0000:8a2e:0370:7334'),
        );
    }

    #[DataProvider('getInvalidReservedIpsV6')]    public function testInvalidReservedIpsV6($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V6_NO_RES,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidReservedIpsV6()
    {
        // Quoting after official filter documentation:
        // "FILTER_FLAG_NO_RES_RANGE = This flag does not apply to IPv6 addresses."
        // Full description: http://php.net/manual/en/filter.filters.flags.php
        return self::getInvalidIpsV6();
    }

    #[DataProvider('getInvalidPublicIpsV6')]    public function testInvalidPublicIpsV6($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::V6_ONLY_PUBLIC,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPublicIpsV6()
    {
        return array_merge(self::getInvalidPrivateIpsV6(), self::getInvalidReservedIpsV6());
    }

    #[DataProvider('getInvalidIpsAll')]    public function testInvalidIpsAll($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::ALL,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidIpsAll()
    {
        return array_merge(self::getInvalidIpsV4(), self::getInvalidIpsV6());
    }

    #[DataProvider('getInvalidPrivateIpsAll')]    public function testInvalidPrivateIpsAll($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::ALL_NO_PRIV,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPrivateIpsAll()
    {
        return array_merge(self::getInvalidPrivateIpsV4(), self::getInvalidPrivateIpsV6());
    }

    #[DataProvider('getInvalidReservedIpsAll')]    public function testInvalidReservedIpsAll($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::ALL_NO_RES,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidReservedIpsAll()
    {
        return array_merge(self::getInvalidReservedIpsV4(), self::getInvalidReservedIpsV6());
    }

    #[DataProvider('getInvalidPublicIpsAll')]    public function testInvalidPublicIpsAll($ip)
    {
        $constraint = new Ip(array(
            'version' => Ip::ALL_ONLY_PUBLIC,
            'message' => 'myMessage',
        ));

        $this->validator->validate($ip, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$ip.'"')
            ->setCode(Ip::INVALID_IP_ERROR)
            ->assertRaised();
    }

    public static function getInvalidPublicIpsAll()
    {
        return array_merge(self::getInvalidPublicIpsV4(), self::getInvalidPublicIpsV6());
    }
}
