<?php
/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2021 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access this file directly");
}

/**
 * @since 10.0.0
 */
class ManualLink extends CommonDBChild {

   public $dohistory              = false;
   public $auto_message_on_action = false; // Link in message can't work'
   protected $displaylist         = false;
   static public $logs_for_parent = true;
   static public $itemtype        = 'itemtype';
   static public $items_id        = 'items_id';

   static function getTypeName($nb = 0) {
      return _n('Manual link', 'Manual links', $nb);
   }

   function getLogTypeID() {
      return [$this->fields['itemtype'], $this->fields['items_id']];
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      $count = 0;
      if ($_SESSION['glpishow_count_on_tabs'] && !$item->isNewItem()) {
         $count += countElementsInTable(
            'glpi_manuallinks',
            [
               'itemtype'  => $item->getType(),
               'items_id'  => $item->fields[$item->getIndexName()],
            ]
         );
         if (Link::canView()) {
            $count += countElementsInTable(
               ['glpi_links_itemtypes', 'glpi_links'],
               [
                  'glpi_links_itemtypes.links_id'  => new \QueryExpression(DB::quoteName('glpi_links.id')),
                  'glpi_links_itemtypes.itemtype'  => $item->getType()
               ] + getEntitiesRestrictCriteria('glpi_links', '', '', false)
            );
         }
      }
      return self::createTabEntry(_n('Link', 'Links', Session::getPluralNumber()), $count);
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      self::showForItem($item);
      Link::showForItem($item);

      return true;
   }

   function showForm($ID, $options = []) {

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      if ($this->isNewItem()) {
         echo Html::hidden('itemtype', ['value' => $options['itemtype']]);
         echo Html::hidden('items_id', ['value' => $options['items_id']]);
      }

      echo '<tr class="tab_bg_1">';
      echo '<td>';
      echo __('Name');
      echo '</td>';
      echo '<td>';
      echo Html::input('name', ['value' => $this->fields['name']]);
      echo '</td>';
      echo '<td rowspan="4">';
      echo __('Comments');
      echo '</td>';
      echo '<td rowspan="4">';
      Html::textarea(
         [
            'name'  => 'comment',
            'cols'  => 50,
            'rows'  => 8,
            'value' => $this->fields['comment'],
         ]
      );
      echo '</td>';
      echo '</tr>';

      echo '<tr class="tab_bg_1">';
      echo '<td>';
      echo __('URL');
      echo '</td>';
      echo '<td>';
      echo Html::input('url', ['value' => $this->fields['url']]);
      echo '</td>';
      echo '</tr>';

      echo '<tr class="tab_bg_1">';
      echo '<td>';
      echo __('Open in a new window');
      echo '</td>';
      echo '<td>';
      Dropdown::showYesNo('open_window', $this->fields['open_window']);
      echo '</td>';
      echo '</tr>';

      echo '<tr class="tab_bg_1">';
      echo '<td>';
      echo __('Icon');
      echo '</td>';
      echo '<td>';
      $icon_selector_id = 'icon_' . mt_rand();
      echo Html::select(
         'icon',
         [$this->fields['icon'] => $this->fields['icon']],
         [
            'id'       => $icon_selector_id,
            'selected' => $this->fields['icon'],
            'style'    => 'width:175px;'
         ]
      );
      echo '</td>';
      echo '</tr>';
      echo Html::script('js/Forms/FaIconSelector.js');
      echo Html::scriptBlock(<<<JAVASCRIPT
         $(
            function() {
               var icon_selector = new GLPI.Forms.FaIconSelector(document.getElementById('{$icon_selector_id}'));
               icon_selector.init();
            }
         );
JAVASCRIPT
      );

      $this->showFormButtons($options);

      return true;
   }

   function prepareInputForAdd($input) {
      if (!array_key_exists('url', $input) || empty($input['url'])) {
         Session::addMessageAfterRedirect(
            __('URL is required'),
            false,
            ERROR
         );
         return false;
      }

      return $this->prepareInput($input);
   }

   function prepareInputForUpdate($input) {
      return $this->prepareInput($input);
   }

   /**
    * Prepare input (for add/update).
    *
    * @return array|false
    */
   private function prepareInput(array $input) {
      if (array_key_exists('url', $input) && !empty($input['url']) && !Toolbox::isValidWebUrl($input['url'])) {
         Session::addMessageAfterRedirect(
            __('Invalid URL'),
            false,
            ERROR
         );
         return false;
      }

      return $input;
   }

   /**
    * Show manual links for an item.
    *
    * @return void
    */
   private static function showForItem(CommonDBTM $item): void {
      global $DB;

      if (!self::canView() || $item->isNewItem()) {
         return;
      }

      $iterator = $DB->request([
         'FROM'         => 'glpi_manuallinks',
         'WHERE'        => [
            'itemtype'  => $item->getType(),
            'items_id'  => $item->fields[$item->getIndexName()],
         ],
         'ORDERBY'      => 'name'
      ]);

      echo '<div class="spaced">';
      echo '<table class="tab_cadrehov">';
      echo '<tr>';
      echo '<th colspan="2">';
      echo self::getTypeName(Session::getPluralNumber());
      echo '</th>';
      echo '<th class="right">';
      // Create a fake link to check rights.
      // This is mandatory as CommonDBChild needs to know itemtype and items_id to compute rights.
      $link = new self();
      $link->fields['itemtype'] = $item->getType();
      $link->fields['items_id'] = $item->fields[$item->getIndexName()];
      if ($link->canCreateItem()) {
         $form_url = self::getFormURL() . '?itemtype=' . $item->getType() . '&items_id=' . $item->fields[$item->getIndexName()];
         echo '<a class="vsubmit" href="' . $form_url . '">';
         echo '<i class="fas fa-plus"></i>&nbsp;';
         echo _x('button', 'Add');
         echo '</a>';
      }
      echo '</th>';
      echo '</tr>';

      if (count($iterator)) {
         foreach ($iterator as $row) {
            $link->getFromResultSet($row);

            echo '<tr class="tab_bg_2">';
            echo '<td>';
            echo self::getLinkHtml($row);
            echo '</td>';
            echo '<td>';
            echo $row['comment'];
            echo '</td>';
            echo '<td class="right">';
            if ($link->canUpdateItem()) {
               echo '<a class="pointer" href="' . self::getFormURLWithID($row[$item->getIndexName()]) . '" title="' . _sx('button', 'Update') . '">';
               echo '<i class="fas fa-edit"></i>&nbsp;';
               echo '<span class="sr-only">' . _x('button', 'Update') . '</span>';
               echo '</a>';
               echo '&nbsp;';
            }
            if ($link->canDeleteItem()) {
               echo '<form action="'. self::getFormURL() .'" method="post" style="display:inline-block;">';
               echo Html::hidden('id', ['value' =>  $row[$item->getIndexName()]]);
               echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
               echo Html::hidden('delete', ['value' => 1]);
               $confirm_js = 'if (window.confirm(\'' . __s('You are about to delete this item. Do you confirm?') . '\')) { '
                  . 'this.parentNode.submit();'
                  . ' }';
               echo '<a class="pointer" href="#" onclick="'. $confirm_js . '" title="' . _sx('button', 'Delete') . '">';
               echo '<i class="fas fa-times"></i>&nbsp;';
               echo '<span class="sr-only">' . _x('button', 'Delete') . '</span>';
               echo '</a>';
               echo '</form>';
            }
            echo '</td>';
            echo '</tr>';
         }
      } else {
         echo '<tr class="tab_bg_2">';
         echo '<td colspan="3">';
         echo __('No link defined');
         echo '</td>';
         echo '</tr>';
      }

      echo '</table>';
      echo '</div>';
   }

   static function rawSearchOptionsToAdd($itemtype = null) {
      $tab = [];

      $tab[] = [
         'id'                 => '146',
         'table'              => static::getTable(),
         'field'              => '_virtual',
         'name'               => self::getTypeName(Session::getPluralNumber()),
         'datatype'           => 'specific',
         'additionalfields'   => [
            'id',
            'name',
            'url',
            'open_window',
            'icon',
         ],
         'forcegroupby'       => true,
         'nosearch'           => true,
         'nosort'             => true,
         'massiveaction'      => false,
         'joinparams'         => [
            'jointype'           => 'itemtype_item',
         ],
      ];

      return $tab;
   }

   static function getSpecificValueToDisplay($field, $values, array $options = []) {

      if (!is_array($values)) {
         $values = [$field => $values];
      }
      switch ($field) {
         case '_virtual':
            return self::getLinkHtml($values);
            break;
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }

   /**
    * Returns link HTML code.
    *
    * @param array $fields
    *
    * @return string
    */
   private static function getLinkHtml(array $fields): string {

      $html ='';

      $target = $fields['open_window'] == 1 ? '_blank' : '_self';
      $html .= '<a href="' . Html::entities_deep($fields['url']) . '" target="' . $target . '">';
      if (!empty($fields['icon'])) {
         $html .= '<i class="fa-lg fa-fw fas ' . Html::entities_deep($fields['icon']) . '"'
            . ' style="font-family:\'Font Awesome 5 Free\', \'Font Awesome 5 Brands\';"></i>&nbsp;';
      }
      $html .= !empty($fields['name']) ? $fields['name'] : $fields['url'];
      $html .= '</a>';

      return $html;
   }

   static function getIcon() {
      return "fas fa-link";
   }
}
