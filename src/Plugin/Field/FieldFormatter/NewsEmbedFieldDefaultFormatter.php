<?php

/**
 * @file
 * Contains \Drupal\news_embed_field\Plugin\field\formatter\SnippetsDefaultFormatter.
 */

namespace Drupal\news_embed_field\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\news_embed_field\Controller\Helpers;
use DOMDocument;


/**
 * Plugin implementation of the 'news_embed_field_default' formatter.
 *
 * @FieldFormatter(
 *   id = "news_embed_field_default",
 *   label = @Translation("News embed field default"),
 *   field_types = {
 *     "news_embed_field"
 *   }
 * )
 */
class NewsEmbedFieldDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = array();
    foreach ($items as $delta => $item) {
      // Render output
      if (filter_var($item->url, FILTER_VALIDATE_URL)) {
        $output = PHP_EOL;
        $embeddedPage = $this->parseEmbeddedPage($item->url);
        if ($embeddedPage['response_code'] == 200 && !empty($embeddedPage['article'])) {
          if (!empty($item->local_info)) {
            $output .= '<div class="local_info">' . $item->local_info . '</div>' . PHP_EOL;
          }
          $output .= '<div class="embedded_article">' . $embeddedPage['article'] . '</div>' . PHP_EOL;
          $output .= '<div class="embedded_article">' . htmlentities($embeddedPage['article']) . '</div>' . PHP_EOL;
        }
        $elements[$delta] = array('#markup' => $output);
      }
    }
    return $elements;
  }

  private function parseEmbeddedPage($url) {
    $results = array();

    //open connection
    $ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, $url);

    //So that curl_exec returns the contents of the cURL; rather than echoing it
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

    //execute post
    $html = curl_exec($ch);

    $results['url'] = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $results['response_code'] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $results['html'] = $html;
    $results['canonical'] = $this->getCanonicalURL($html, $results['url']);
    $results['article'] = $this->getArticle($html, $results['url']);

    return $results;
  }

  private function getCanonicalURL($html, $url) {
    if($html) {
      $dom = $this->load_html($html);
      if($dom) {
        $links = $dom->getElementsByTagName('link');
        foreach($links as $link) {
          $rels = [];
          if($link->hasAttribute('rel') && ($relAtt = $link->getAttribute('rel')) !== '') {
            $rels = preg_split('/\s+/', trim($relAtt));
          }
          if(in_array('canonical', $rels)) {
            $url = $link->getAttribute('href');
          }
        }
      }
    }
    return $url;
  }

  private function getArticle($html, $url) {
    $results = '';
    $parsedURL = parse_url($url);
    if($html) {
      $dom = $this->load_html($html);
      if($dom) {
        $articles = $dom->getElementsByTagName('article');
        if (count($articles)) {
          $results = $articles[0]->ownerDocument->saveXML($articles[0]);
        }
      }
    }

    $results = str_replace('src="//', 'src="deleteme//', $results);
    $results = str_replace('src="/', 'src="' . $parsedURL['scheme'] . '://' . $parsedURL['host'] . '/', $results);
    $results = str_replace('href="/', 'href="' . $parsedURL['scheme'] . '://' . $parsedURL['host'] . '/', $results);
    $results = str_replace('src="deleteme//', 'src="//', $results);

    return $results;
  }

  private function load_html($html) {
    $dom = new DOMDocument;
    libxml_use_internal_errors(true); // suppress parse errors and warnings
    // Force interpreting this as UTF-8
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING|LIBXML_NOERROR);
    libxml_clear_errors();
    return $dom;
  }
}
