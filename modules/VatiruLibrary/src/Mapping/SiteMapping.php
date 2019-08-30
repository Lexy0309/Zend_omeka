<?php
namespace VatiruLibrary\Mapping;

use CSVImport\Mapping\AbstractMapping;
use Omeka\Api\Adapter\SiteSlugTrait;
use Omeka\Api\Exception\NotFoundException;
use Omeka\Site\Theme\Manager as ThemeManager;
use Omeka\Stdlib\Message;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

class SiteMapping extends AbstractMapping
{
    use SiteSlugTrait;

    protected $label = 'Site'; // @translate
    protected $name = 'site-data';

    /**
     * @var ThemeManager
     */
    protected $themes;

    public function init(array $args, ServiceLocatorInterface $serviceLocator)
    {
        parent::init($args, $serviceLocator);
        $this->themes = $serviceLocator->get('Omeka\Site\ThemeManager');
    }

    public function getSidebar(PhpRenderer $view)
    {
        return $view->partial('common/admin/site-mapping-sidebar');
    }

    public function processRow(array $row)
    {
        // Reset the data and the map between rows.
        $this->setHasErr(false);
        $this->data = [];
        $this->map = [];

        $this->processGlobalArgs();

        $multivalueMap = isset($this->args['column-multivalue']) ? $this->args['column-multivalue'] : [];
        foreach ($row as $index => $values) {
            if (array_key_exists($index, $multivalueMap) && strlen($multivalueMap[$index])) {
                $values = explode($multivalueMap[$index], $values);
                $values = array_map(function ($v) {
                    return trim($v, "\t\n\r   ");
                }, $values);
            } else {
                $values = [$values];
            }
            $values = array_filter($values, 'strlen');
            if ($values) {
                $this->processCell($index, $values);
            }
        }

        $this->checkRow();

        return $this->data;
    }

    protected function processGlobalArgs()
    {
        $data = &$this->data;

        // Set columns.
        if (isset($this->args['column-owner_email'])) {
            $this->map['ownerEmail'] = $this->args['column-owner_email'];
            $data['o:owner'] = null;
        }
        if (isset($this->args['column-is_public'])) {
            $this->map['isPublic'] = $this->args['column-is_public'];
            $data['o:is_public'] = null;
        }

        if (isset($this->args['column-site'])) {
            $this->map['site'] = $this->args['column-site'];
            foreach ($this->args['column-site'] as $column) {
                if ($column === 'o:item_set' || $column === 'o:permission') {
                    $data[$column] = [];
                } else {
                    $data[$column] = null;
                }
            }
        }

        // Set default values.
        if (!empty($this->args['o:owner']['o:id'])) {
            $data['o:owner'] = ['o:id' => (int) $this->args['o:owner']['o:id']];
        }
        if (isset($this->args['o:is_public']) && strlen($this->args['o:is_public'])) {
            $data['o:is_public'] = (bool) $this->args['o:is_public'];
        }

        // Set the default theme by default.
        $data['o:theme'] = 'default';

        // Set the default item pool. It avoids the item pool to be an empty
        // array in the api, instead of an empty object.
        $data['o:item_pool'] = [
            'resource_class_id' => '',
        ];

        // TODO Add default values for item sets and permissions.
    }

    /**
     * Process the content of a cell (one csv value).
     *
     * @param int $index
     * @param array $values The content of the cell as an array (only one value
     * if the cell is not multivalued).
     */
    protected function processCell($index, array $values)
    {
        $data = &$this->data;

        if (isset($this->map['ownerEmail'][$index])) {
            $user = $this->findUser(reset($values));
            if ($user) {
                $data['o:owner'] = ['o:id' => $user->id()];
            }
        }

        if (isset($this->map['isPublic'][$index])) {
            $value = reset($values);
            if (strlen($value)) {
                $data['o:is_public'] = in_array(strtolower($value), ['false', 'no', 'off', 'private'])
                    ? false
                    : (bool) $value;
            }
        }

        if (isset($this->map['site'][$index])) {
            switch ($this->map['site'][$index]) {
                case 'o:slug':
                    $slug = $this->slugify(reset($values));
                    if (strlen($slug)) {
                        $data['o:slug'] = $slug;
                    }
                    break;

                case 'o:theme':
                    $theme = $this->checkTheme(reset($values));
                    if ($theme) {
                        $data['o:theme'] = $theme;
                    }
                    break;

                case 'o:title':
                    $data['o:title'] = reset($values);
                    break;

                /*
                case 'o:owner':
                     $data['o:owner'] = $owner;
                     break;
                 */

                case 'o:is_public':
                    $value = strtolower(reset($values));
                    if (strlen($value)) {
                        $data['o:is_public'] = in_array($value, ['false', 'no', 'off', 'private'])
                            ? false
                            : (bool) $value;
                    }
                    break;
            }
        }
    }

    protected function checkRow()
    {
        $data = $this->data;

        if (!strlen($data['o:slug']) && !strlen($data['o:title'])) {
            $this->logger->err('A site must have a title or a slug.'); // @translate
            $this->setHasErr(true);
        } else {
            if (!strlen($data['o:slug'])) {
                $data['o:slug'] = $this->getAutomaticSlug($data['o:title']);
            } elseif (!strlen($data['o:title'])) {
                $data['o:title'] = $data['o:slug'];
            }
            switch ($this->args['action']) {
                case \CSVImport\Job\Import::ACTION_CREATE:
                    $site = $this->findSite($data['o:slug']);
                    if ($site) {
                        $this->logger->err(new Message('Site "%s" exists and cannot be created.', $data['o:slug'])); // @translate
                        $this->setHasErr(true);
                    }
                    break;
            }
        }
    }

    protected function findUser($email)
    {
        $response = $this->api->search('users', ['email' => $email]);
        $content = $response->getContent();
        if (empty($content)) {
            $this->logger->err(new Message('"%s" is not a valid user email address.', $email)); // @translate
            $this->setHasErr(true);
            return false;
        }
        $user = $content[0];
        $userEmail = $user->email();
        if (strtolower($email) != strtolower($userEmail)) {
            $this->logger->err(new Message('"%s" is not a valid user email address.', $email)); // @translate
            $this->setHasErr(true);
            return false;
        }
        return $content[0];
    }

    protected function findSite($slug)
    {
        // Api doesn't allow to search site by slug.
        try {
            $site = $this->api->read('sites', ['slug' => $slug])->getContent();
            return $site;
        } catch (NotFoundException $e) {
        }
    }

    protected function checkTheme($theme)
    {
        $themes = $this->themes->getThemes();
        foreach ($themes as $themeEntity) {
            if ($themeEntity->getId() === $theme) {
                return $theme;
            }
        }
        $this->logger->err(new Message('"%s" is not a valid theme.', $theme)); // @translate
        $this->setHasErr(true);
        return false;
    }
}
