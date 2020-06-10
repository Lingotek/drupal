<?php

namespace Drupal\lingotek\Remote;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Lingotek HTTP interface.
 */
interface LingotekHttpInterface extends ContainerInjectionInterface {

  /**
   * Send a GET request.
   *
   * @param string|\Psr\Http\Message\UriInterface $path
   *   URI object or string.
   * @param array $args
   *   Request argument to add via query string.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function get($path, $args = []);

  /**
   * Send a POST request.
   *
   * @param string|\Psr\Http\Message\UriInterface $path
   *   URI object or string.
   * @param array $args
   *   Request arguments to the POST request.
   * @param bool $use_multipart
   *   If TRUE, use multipart post arguments. If FALSE, uses form parameters.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function post($path, $args = [], $use_multipart = FALSE);

  /**
   * Send a DELETE request.
   *
   * @param string|\Psr\Http\Message\UriInterface $path
   *   URI object or string.
   * @param array $args
   *   Request argument to add via query string.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function delete($path, $args = []);

  /**
   * Send a PATCH request.
   *
   * @param string|\Psr\Http\Message\UriInterface $path
   *   URI object or string.
   * @param array $args
   *   Request arguments to the PATCH request.
   * @param bool $use_multipart
   *   If TRUE, use multipart post arguments. If FALSE, uses form parameters.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A response.
   */
  public function patch($path, $args = [], $use_multipart = FALSE);

  /**
   * Gets the current configured token.
   *
   * @return string
   *   The token.
   */
  public function getCurrentToken();

}
