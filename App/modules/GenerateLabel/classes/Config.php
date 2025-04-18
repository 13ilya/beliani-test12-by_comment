<?php
namespace label;

use label\Config\ConfigPDF;

class Config extends Abstract_DataValue
{
    /** @var  string */
    protected $rootDirectory;
    /** @var  Registry */
    protected $registry;
    /** @var  string */
    protected $logDirectory;

    /** @var  ConfigPDF */
    protected $pdf;
    /** @var  Smarty */
    protected $smarty;

    /**
     * Config constructor.
     * @param $rootDir
     */
    public function __construct($rootDir)
    {
        $this->rootDirectory = $rootDir;
    }

    /**
     * @return string
     */
    public function getRootDirectory()
    {
        return $this->rootDirectory;
    }

    /**
     * @param Registry $registry
     * @return Config
     */
    public function setRegistry(Registry $registry)
    {
        $this->registry = $registry;
        return $this;
    }

    /**
     * @return Registry
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @param $logSubDir
     * @return Config
     */
    public function setLogDirectory($logSubDir)
    {
        $this->logDirectory = $this->getRootDirectory() . DIRECTORY_SEPARATOR . $logSubDir . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * @return string
     */
    public function getLogDirectory()
    {
        return $this->logDirectory;
    }

    /**
     * @param ConfigPDF $PDF
     * @return Config
     */
    public function setPDF(ConfigPDF $PDF)
    {
        $this->pdf = $PDF;
        return $this;
    }

    /**
     * @return ConfigPDF
     */
    public function getPdf()
    {
        return $this->pdf;
    }

    /**
     * @param Smarty $smarty
     * @return Config
     */
    public function setSmarty(\Smarty $smarty)
    {
        $this->smarty = $smarty;
        return $this;
    }

    /**
     * @return Smarty
     */
    public function getSmarty()
    {
        return $this->smarty;
    }

}
