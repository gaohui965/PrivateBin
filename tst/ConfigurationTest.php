<?php

use PrivateBin\Configuration;

class ConfigurationTest extends PHPUnit_Framework_TestCase
{
    private $_minimalConfig;

    private $_options;

    private $_path;

    public function setUp()
    {
        /* Setup Routine */
        Helper::confBackup();
        $this->_minimalConfig                   = '[main]' . PHP_EOL . '[model]' . PHP_EOL . '[model_options]';
        $this->_options                         = Configuration::getDefaults();
        $this->_options['model_options']['dir'] = PATH . $this->_options['model_options']['dir'];
        $this->_options['traffic']['dir']       = PATH . $this->_options['traffic']['dir'];
        $this->_options['purge']['dir']         = PATH . $this->_options['purge']['dir'];
        $this->_path                            = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'privatebin_cfg';
        if (!is_dir($this->_path)) {
            mkdir($this->_path);
        }
    }

    public function tearDown()
    {
        /* Tear Down Routine */
        Helper::rmDir($this->_path);
        if (is_file(CONF)) {
            unlink(CONF);
        }
        Helper::confRestore();
    }

    public function testDefaultConfigFile()
    {
        $conf = new Configuration;
        $this->assertEquals($this->_options, $conf->get(), 'default configuration is correct');
    }

    public function testHandleFreshConfigFile()
    {
        Helper::createIniFile(CONF, $this->_options);
        $conf = new Configuration;
        $this->assertEquals($this->_options, $conf->get(), 'newly generated configuration is correct');
    }

    public function testHandleMissingConfigFile()
    {
        if (is_file(CONF)) {
            unlink(CONF);
        }
        $conf = new Configuration;
        $this->assertEquals($this->_options, $conf->get(), 'returns correct defaults on missing file');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 2
     */
    public function testHandleBlankConfigFile()
    {
        file_put_contents(CONF, '');
        new Configuration;
    }

    public function testHandleMinimalConfigFile()
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration;
        $this->assertEquals($this->_options, $conf->get(), 'returns correct defaults on empty file');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 3
     */
    public function testHandleInvalidSection()
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration;
        $conf->getKey('foo', 'bar');
    }

    /**
     * @expectedException Exception
     * @expectedExceptionCode 4
     */
    public function testHandleInvalidKey()
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration;
        $conf->getKey('foo');
    }

    public function testHandleGetKey()
    {
        file_put_contents(CONF, $this->_minimalConfig);
        $conf = new Configuration;
        $this->assertEquals($this->_options['main']['sizelimit'], $conf->getKey('sizelimit'), 'get default size');
    }

    public function testHandleWrongTypes()
    {
        $original_options                                    = $this->_options;
        $original_options['main']['syntaxhighlightingtheme'] = 'foo';
        $options                                             = $original_options;
        $options['main']['discussion']                       = 'true';
        $options['main']['opendiscussion']                   = 0;
        $options['main']['password']                         = -1; // evaluates to TRUE
        $options['main']['fileupload']                       = 'false';
        $options['expire_options']['foo']                    = 'bar';
        $options['formatter_options'][]                      = 'foo';
        Helper::createIniFile(CONF, $options);
        $conf                                      = new Configuration;
        $original_options['expire_options']['foo'] = intval('bar');
        $original_options['formatter_options'][0]  = 'foo';
        $this->assertEquals($original_options, $conf->get(), 'incorrect types are corrected');
    }

    public function testHandleMissingSubKeys()
    {
        $options = $this->_options;
        unset($options['expire_options']['1week']);
        unset($options['expire_options']['1year']);
        unset($options['expire_options']['never']);
        Helper::createIniFile(CONF, $options);
        $conf                         = new Configuration;
        $options['expire']['default'] = '5min';
        $this->assertEquals($options, $conf->get(), 'not overriding "missing" subkeys');
    }

    public function testHandlePreRenameConfig()
    {
        $options                   = $this->_options;
        $options['model']['class'] = 'zerobin_data';
        Helper::createIniFile(CONF, $options);
        $conf = new Configuration;
        $this->assertEquals('Filesystem', $conf->getKey('class', 'model'), 'old data class gets renamed');

        $options['model']['class'] = 'zerobin_db';
        Helper::createIniFile(CONF, $options);
        $conf = new Configuration;
        $this->assertEquals('Database', $conf->getKey('class', 'model'), 'old db class gets renamed');
    }

    public function testHandleConfigFileRename()
    {
        $options  = $this->_options;
        Helper::createIniFile(PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini.sample', $options);

        $options['main']['opendiscussion'] = true;
        $options['main']['fileupload']     = true;
        $options['main']['template']       = 'darkstrap';
        Helper::createIniFile(PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini', $options);

        $conf = new Configuration;
        $this->assertFileExists(CONF, 'old configuration file gets converted');
        $this->assertFileNotExists(PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini', 'old configuration file gets removed');
        $this->assertFileNotExists(PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini.sample', 'old configuration sample file gets removed');
        $this->assertTrue(
            $conf->getKey('opendiscussion') &&
            $conf->getKey('fileupload') &&
            $conf->getKey('template') === 'darkstrap',
            'configuration values get converted'
        );
    }

    public function testRenameIniSample()
    {
        $iniSample = PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini.sample';

        Helper::createIniFile(PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini', $this->_options);
        if (is_file(CONF)) {
            unlink(CONF);
        }
        rename(CONF_SAMPLE, $iniSample);
        new Configuration;
        $this->assertFileNotExists($iniSample, 'old sample file gets removed');
        $this->assertFileExists(CONF_SAMPLE, 'new sample file gets created');
        $this->assertFileExists(CONF, 'old configuration file gets converted');
        $this->assertFileNotExists(PATH . 'cfg' . DIRECTORY_SEPARATOR . 'conf.ini', 'old configuration file gets removed');
    }

    public function testConfigPath()
    {
        // setup
        $configFile              = $this->_path . DIRECTORY_SEPARATOR . 'conf.php';
        $options                 = $this->_options;
        $options['main']['name'] = 'OtherBin';
        Helper::createIniFile($configFile, $options);

        // test
        putenv('CONFIG_PATH=' . $this->_path);
        $conf = new Configuration;
        $this->assertEquals('OtherBin', $conf->getKey('name'), 'changing config path is supported');

        // cleanup environment
        if (is_file($configFile)) {
            unlink($configFile);
        }
        putenv('CONFIG_PATH');
    }

    public function testConfigPathIni()
    {
        // setup
        $configFile              = $this->_path . DIRECTORY_SEPARATOR . 'conf.ini';
        $configMigrated          = $this->_path . DIRECTORY_SEPARATOR . 'conf.php';
        $options                 = $this->_options;
        $options['main']['name'] = 'OtherBin';
        Helper::createIniFile($configFile, $options);
        $this->assertFileNotExists(CONF, 'configuration in the default location is non existing');

        // test
        putenv('CONFIG_PATH=' . $this->_path);
        $conf = new Configuration;
        $this->assertEquals('OtherBin', $conf->getKey('name'), 'changing config path is supported for ini files as well');
        $this->assertFileExists($configMigrated, 'old configuration file gets converted');
        $this->assertFileNotExists($configFile, 'old configuration file gets removed');
        $this->assertFileNotExists(CONF, 'configuration is not created in the default location');

        // cleanup environment
        if (is_file($configFile)) {
            unlink($configFile);
        }
        putenv('CONFIG_PATH');
    }
}
