<?php

namespace GuestUser\View\Helper;

use Zend\View\Helper\AbstractHtmlElement;

class GuestUserWidget extends AbstractHtmlElement
{
    public function __invoke($widget)
    {
        $escape = $this->getView()->plugin('escapeHtml');

        if (is_array($widget)) {
            $attribs = [
                'class' => 'guest-user-widget-label',
            ];

            $html = '<h2' . $this->htmlAttribs($attribs) . '>';
            $html .= $escape($widget['label']);
            $html .= '</h2>';
            $html .= $widget['content'];

            return $html;
        } else {
            return $widget;
        }
    }
}
