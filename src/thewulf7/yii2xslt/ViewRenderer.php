<?php
namespace thewulf7\yii2xslt;

use Yii;
use yii\base\View;
use yii\base\ViewRenderer as BaseViewRenderer;

/**
 * Class ViewRenderer
 *
 * @package thewulf7\yii2xslt
 */
class ViewRenderer extends BaseViewRenderer
{

    /**
     * @var \DOMDocument
     */
    private $domXSL;

    /**
     * @var \DOMDocument
     */
    private $domXML;

    /**
     * @var array
     */
    private $additionalVariables;

    /**
     * Instantiates and configures the xslt object.
     */
    public function init()
    {
        $this->domXML               = new \DOMDocument("1.0", "utf-8");
        $this->domXML->formatOutput = 'xml';

        $this->domXSL                     = new \DOMDocument('1.0', 'utf-8');
        $this->domXSL->resolveExternals   = true;
        $this->domXSL->substituteEntities = true;
    }

    /**
     * Renders a view file.
     *
     * This method is invoked by [[View]] whenever it tries to render a view.
     * Child classes must implement this method to render the given view file.
     *
     * @param View   $view   the view object used for rendering the file.
     * @param string $file   the view file.
     * @param array  $params the parameters to be passed to the view file.
     *
     * @return string the rendering result
     */
    public function render($view, $file, $params)
    {
        $this->prepareXSL($file);
        $this->prepareXML($params);

        $xslt = new \xsltProcessor;
        $xslt->registerPHPFunctions();
        $xslt->importStylesheet($this->domXSL);

        // Additional variables
        if (is_array($this->additionalVariables))
        {
            $this->addRequestParams($xslt, $this->additionalVariables);
        }

        $this->addRequestParams($xslt, $_COOKIE);
        $this->addRequestParams($xslt, $_REQUEST);
        $this->addRequestParams($xslt, $_SERVER, '_');

        return $xslt->transformToXML($this->domXML);
    }

    /**
     * Prepare template
     *
     * @param mixed $templatesSource - path to the template
     *
     * @throws \RuntimeException
     */
    protected function prepareXSL($templatesSource)
    {
        if (!is_file($templatesSource))
        {
            throw new \RuntimeException('Not found template "' . $templatesSource . '".');
        }

        $this->domXSL->load($templatesSource, LIBXML_COMPACT);
    }

    /**
     * Prepare data
     *
     * @param $variables
     *
     * @throws \RuntimeException
     */
    protected function prepareXML($variables)
    {
        if ($variables instanceof \DOMDocument)
        {
            $this->domXML = $variables;
        } else
        {
            $xml = $this->xml_encode($variables, true);
            $this->domXML = dom_import_simplexml($xml);
        }

//        $rootNode = $this->domXML->appendChild($this->domXML->createElement("result"));
//        $rootNode->setAttribute('xmlns:xlink', 'http://www.w3.org/TR/xlink');

//        $translator = new xmlTranslator($this->domXML);
//        $translator->translateToXml($rootNode, $variables);
    }

    /**
     * Pass massive to template
     *
     * @param \xsltProcessor $xslt
     * @param                $array
     * @param string         $prefix
     */
    protected function addRequestParams(\xsltProcessor $xslt, $array, $prefix = '')
    {
        foreach ($array as $key => $val)
        {
            $key = strtolower($key);
            if (!is_array($val))
            {
                // Fix to prevent warning on some strings
                if (strpos($val, "'") !== false && strpos($val, "\"") !== false)
                {
                    $val = str_replace("'", "\\\"", $val);
                }
                $key = str_replace([':'], [''], $key);
                $xslt->setParameter('', $prefix . $key, $val);
            } else
            {
                $this->addRequestParams($xslt, $val, $prefix . $key . ".");
            }
        }
    }

    /**
     * Convert data to XML
     *
     * @param $array
     * @param null|\SimpleXMLElement $node
     *
     * @return null|\SimpleXMLElement
     */
    private function xml_encode($array, $as_attributes = false, $node = null) {
        if (!isset($node))
        {
            $node = new \SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\"?><page></page>");
        }
        foreach ($array as $key => $value)
        {
            if (is_numeric($key))
            {
                $key = 'item' . $key;
            }
            if (is_array($value))
            {
                $subnode = $node->addChild($key);
                $this->xml_encode($value, $as_attributes, $subnode);
            } else
            {
                if ($as_attributes)
                {
                    $node->addAttribute($key, $value);
                } else
                {
                    $node->addChild($key, $value);
                }
            }
        }
        return $node;
    }
}
