<?php

/*
Plugin Name: Schufa Identity Check
Plugin URI: https://identitaetscheck-plugin.de
Description: Gemäß dem seit 01.04.2016 geltendem Gesetz für Onlinehandel mit Waren, die nicht an Jugendliche unter 18 Jahren abgegeben werden dürfen, wird mit diesem Plugin eine Schufa Abfrage für das Produkt Identitätscheck Premium durchgeführt. Schlägt diese Abfrage fehl, wird der Kauf mit einer Fehlermeldung abgebrochen.
Version: 3.1.3
Author: Hendrik Bäker
Author URI: http://baeker-it.de
License: GNU GPL v2
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

register_activation_hook( __FILE__, 'SCHUFA_IDCheck_install' );

function SCHUFA_IDCheck_install() {
	global $wpdb;

	$table_name = $wpdb->prefix . 'identitycheck_requests';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
id bigint(9) NOT NULL AUTO_INCREMENT,
time DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
user_id MEDIUMINT(9) NOT NULL,
first_name TEXT NOT NULL,
last_name TEXT NOT NULL,
street TEXT NOT NULL,
zipcode TEXT NOT NULL,
city TEXT NOT NULL,
birthdate TEXT NOT NULL,
result BOOLEAN NOT NULL DEFAULT false,
PRIMARY KEY (id)
) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql );

	update_option( 'SCHUFA_ID_CHECK_DB_VERSION', '1.0' );
}

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) && function_exists( 'openssl_pkcs12_read' ) && function_exists( 'curl_init' ) ) {
	require plugin_dir_path( __FILE__ ) . 'app/api/ds24_api.php';

	class SCHUFA_IDCheck {

		private $url = 'https://port.schufa.de/siml2';

		private $curl;

		public $result;

		private $neededFields = [
			'title',
			'first_name',
			'last_name',
			'street',
			'zipcode',
			'city',
			'birthdate'
		];

		private $xml;

		private $customer = [
			'first_name' => '',
			'last_name'  => '',
			'street'     => '',
			'zipcode'    => '',
			'city'       => '',
			'birthdate'  => ''
		];

		static $schufa_credentials = [];

		static $field_relations = [
			"billing_title"      => 'title',
			"billing_first_name" => 'first_name',
			"billing_last_name"  => 'last_name',
			"billing_birthdate"  => 'birthdate',
			"billing_address_1"  => 'street',
			"billing_postcode"   => 'zipcode',
			"billing_city"       => 'city',
		];

		static $replacements_base = [
			'[TIMESTAMP]',
			'[USER]',
			'[PASS]',
			'[IDENTCHECK_VERSION]',
			'[FIRST_NAME]',
			'[LAST_NAME]',
			'[BIRTHDATE]',
			'[STREET]',
			'[ZIPCODE]',
			'[CITY]',
		];

		const MAX_CHARS = [
			'FIRST_NAME' => 44,
			'LAST_NAME'  => 46,
			'BIRTHDATE'  => 10,
			'STREET'     => 46,
			'ZIPCODE'    => 9,
			'CITY'       => 44
		];

		static $replaces_base = [];

		private $settings = [];

		private $error = false;

		private $digistore;

		private $exceeded = false;

		const ERRORS = [
			'ERRORCODE',
			'ERRORMESSAGE'
		];


		const RESPONSE_FIELDS = [
			'VORNAME'      => 'first_name',
			'NACHNAME'     => 'last_name',
			'GEBURTSDATUM' => 'birthdate',
			'STRASSE'      => 'street',
			'PLZ'          => 'zipcode',
			'ORT'          => 'city',
			'ERROR',
			'ERRORMESSAGE',
			'VERBRAUCHERDATEN',
			'AUSWEISGEPRUEFTEIDENTITAET'
		];

		const SETTINGS = [
			'ID_CHECK_USER'           => 'Teilnehmerkennung',
			'ID_CHECK_PASSWORD'       => 'Onlinepasswort',
			'ID_CHECK_ORDER_ID'       => 'Digistore Bestell ID',
			'ID_CHECK_LICENSE'        => 'Digistore Lizenzschlüssel',
			'ID_CHECK_FIRST_NAME_MAX' => 'Grenzwert Vorname',
			'ID_CHECK_LAST_NAME_MAX'  => 'Grenzwert Nachname',
			'ID_CHECK_STREET_MAX'     => 'Grenzwert Straße & Hausnummer',
			'ID_CHECK_ZIPCODE_MAX'    => 'Grenzwert PLZ',
			'ID_CHECK_CITY_MAX'       => 'Grenzwert Stadt',
			'ID_CHECK_BIRTHDATE_MAX'  => 'Grenzwert Geburtsdatum',
			'ID_CHECK_OVERALL_MAX'    => 'Grenzwert Gesamt',
		];

		const NUMERIC_FIELDS = [
			'ID_CHECK_FIRST_NAME_MAX' => 85.00,
			'ID_CHECK_LAST_NAME_MAX'  => 85.00,
			'ID_CHECK_STREET_MAX'     => 85.00,
			'ID_CHECK_ZIPCODE_MAX'    => 85.00,
			'ID_CHECK_CITY_MAX'       => 85.00,
			'ID_CHECK_BIRTHDATE_MAX'  => 85.00,
			'ID_CHECK_OVERALL_MAX'    => 85.00
		];
		const DEFAULT_FOR_FIELDS = [
			'ID_CHECK_FIRST_NAME_MAX' => 85.00,
			'ID_CHECK_LAST_NAME_MAX'  => 85.00,
			'ID_CHECK_STREET_MAX'     => 85.00,
			'ID_CHECK_ZIPCODE_MAX'    => 85.00,
			'ID_CHECK_CITY_MAX'       => 85.00,
			'ID_CHECK_BIRTHDATE_MAX'  => 85.00,
			'ID_CHECK_OVERALL_MAX'    => 85.00
		];

		const DIGISTORE_API_KEY = '85143-JsUOls1OLR4bPcbyz11pRWEcrO57kMg8b38UqbQw';

		const QBIT = 'AUSWEISGEPRUEFTEIDENTITAET';

		static $translations = [];

		private $passed = false;

		function __construct( $mode = 'check', $data = false ) {
			$this->digistore = DigistoreApi::connect( self::DIGISTORE_API_KEY );
			if ( function_exists( 'get_option' ) and function_exists( 'add_action' ) ) {
				if ( ! get_option( 'ID_CHECK_USER', false ) or ! get_option( 'ID_CHECK_PASSWORD', false ) ) {
					add_action( 'admin_notices', [ $this, 'noSettings' ] );
				}
			} else {
				self::$replaces_base =
					[
						date( 'Y-m-d H:i:s', time() ),
						'',
						'',
						'Premium',
						'Hendrik',
						'Bäker',
						'26.08.1990',
						'Oldenburger Straße 73',
						'26203',
						'Wardenburg'
					];
			}

			if ( function_exists( 'get_option' ) ) {
				self::$schufa_credentials = [
					'teilnehmerkennung'  => get_option( 'ID_CHECK_USER', '000/00000' ),
					'teilnehmerkennwort' => get_option( 'ID_CHECK_PASSWORD', false ),
				];
			} else {
				self::$schufa_credentials = [
					'teilnehmerkennung'  => 000000,
					'teilnehmerkennwort' => 000000
				];
			}
			$this->result = new stdClass();
			$this->registerHooks();
		}

		public function initCheck() {
			$this->collectUserData();
			$checked = $this->customerHasBeenCheckedWithAddress();
			if ( ! $checked and $checked != 2 ) {
				$this->initCurl();
				$this->result = curl_exec( $this->curl );
				file_put_contents( plugin_dir_path( __FILE__ ) . 'debugResult.xml', $this->result );

				return $this->checkCustomer();
			}
			if ( $checked == 2 ) {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( 'Leider ist Ihre Identität nicht bestätigt worden. Bitte wenden Sie sich an den Support', 'error' );
				}

				return false;
			}

			return true;
		}

		public function noSettings() {
			include plugin_dir_path( __FILE__ ) . 'resources/views/admin/errors/no-configuration.php';

			return false;
		}

		public function registerHooks() {
			if ( function_exists( 'add_filter' ) ) {
				add_filter( 'woocommerce_checkout_fields', [ $this, 'order_fields' ] );
			}
			if ( function_exists( 'add_action' ) ) {
				add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'birthdate_update_order_meta' ] );
				add_action( 'woocommerce_checkout_process', [ $this, 'initCheck' ] );
				add_action( 'admin_menu', [ $this, 'menuSetup' ] );
				add_action( 'show_user_profile', [ $this, 'show_SCHUFA_Status' ] );
				add_action( 'edit_user_profile', [ $this, 'show_SCHUFA_Status' ] );
				add_action( 'woocommerce_checkout_process', [ $this, 'add_usermeta' ] );
				add_action( 'woocommerce_admin_order_data_after_billing_address', [
					$this,
					'schufaIdentityCheckBackendDisplay'
				] );
				add_action( 'edit_user_profile_update', [ $this, 'update_user' ] );
				add_action( 'woocommerce_checkout_after_order_review', [ $this, 'datenuebergabeschufa' ] );
				add_action( 'woocommerce_checkout_process', [ $this, 'datenubergabeschufa_process' ] );
				add_action( 'woocommerce_checkout_update_order_meta', [
					$this,
					'datenubergabeschufa_update_order_meta'
				] );
				add_action( 'wp_ajax_updateIDCHECKSettings', [ $this, 'updateSettings' ] );
				add_action('wp_ajax_schufa_id_delete_result', [$this, 'deleteResult']);
				foreach ( self::SETTINGS as $setting ) {
					register_setting( 'schufa_settings', $setting );
				}
			}
		}
		
		public function deleteResult(){
		    $id = filter_input(INPUT_POST, 'id');
		    global $wpdb;
		    $query = 'DELETE FROM '.$wpdb->prefix . 'identitycheck_requests WHERE id = '.$id;
		    $wpdb->query($query);
		    include(plugin_dir_url(__FILE__).'/resources/views/admin/results/ajaxResults.php');
        }

		public function updateSettings() {
			foreach ( self::SETTINGS as $setting => $translation ) {
				if ( filter_input( INPUT_POST, $setting ) != null ) {
					update_option( $setting, filter_input( INPUT_POST, $setting ) );
				}
			}
			update_option( 'ID_CHECK_QBIT', (boolean) filter_input( INPUT_POST, 'QBIT' ) );
		}
        public static function getCounter()
        {
            $order_id = get_option('ID_CHECK_ORDER_ID', null);
            $digistore = DigistoreApi::connect(self::DIGISTORE_API_KEY);
            $result = $digistore->getPurchase($order_id);
            $current_checks = null;
            $exceeded = true;
            if ($result != null) {
                $status = $result->billing_status;
                if ($status == 'paying' or $status == 'reminding') {
                    if (array_key_exists(0, $result->items)) {
                        $limit = null;
                        switch ((double)$result->amount) {
                            case 99:
                                $limit = null;
                                break;
                            case 49:
                                $limit = 500;
                                break;
                            case 29:
                                $limit = 200;
                                break;
                            default:
                                $limit = 0;
                                break;
                        }
                        $current_checks = get_option('ID_CHECKS', null);
                        $month = date('m', time());
                        $year = date('Y', time());
                        if ($current_checks != null and $limit != null) {
                            if (array_key_exists($year, $current_checks)) {
                                if (array_key_exists($month, $current_checks[$year])) {
                                    if ($current_checks[$year][$month] >= $limit) {
                                        $exceeded = true;
                                    } else {
                                        $current_checks[$year][$month] += 1;
                                        $exceeded = false;
                                    }
                                } else {
                                    $current_checks[$year][$month] = 1;
                                    $exceeded = false;
                                }
                            } else {
                                $current_checks[$year][$month] = 1;
                                $exceeded = false;
                            }
                        } elseif ($limit == null) {
                            $current_checks = [];
                            $current_checks[$year][$month] = 1;
                            $exceeded = false;
                        } else {
                            $current_checks = [];
                            $current_checks[$year][$month] += 1;
                            $exceeded = false;
                        }
                        echo "<pre>";
                        var_dump($current_checks);
                        var_dump($exceeded);
                        echo "</pre>";
                        return $exceeded;
                    } else {
                        echo "<pre>";
                        var_dump($current_checks);
                        var_dump($exceeded);
                        echo "</pre>";
                        return false;
                    }

                } else {
                    echo "<pre>";
                    var_dump($current_checks);
                    var_dump($exceeded);
                    echo "</pre>";
                    return false;
                }
            } else {

            }
        }

		private function countChecks( $debug ) {
			$order_id = get_option( 'ID_CHECK_ORDER_ID', null );
			$license  = get_option( 'ID_CHECK_LICENSE', null );
			if ( $order_id != null and $license != null ) {
				try {
					$this->digistore = DigistoreApi::connect( self::DIGISTORE_API_KEY );
					$license_test    = $this->digistore->validateLicenseKey( $order_id, $license );
						$status = $license_test->is_license_valid;
						if ( $status == 'Y' ) {
							$result = $this->digistore->getPurchase( $order_id );
							if ( $result != null ) {
								$status = $result->billing_status;
								if ( $status == 'paying' or $status == 'reminding' ) {
									if ( array_key_exists( 0, $result->items ) ) {
										$limit = null;
										switch ( (double) $result->amount ) {
											case 99:
												$limit = null;
												break;
											case 49:
												$limit = 500;
												break;
											case 29:
												$limit = 200;
												break;
											default:
												$limit = 0;
												break;
										}
										$current_checks = get_option( 'ID_CHECKS', null );
										$month          = date( 'm', time() );
										$year           = date( 'Y', time() );
										if ( $current_checks != null and $limit != null ) {
											if ( array_key_exists( $year, $current_checks ) ) {
												if ( array_key_exists( $month, $current_checks[ $year ] ) ) {
													if ( $current_checks[ $year ][ $month ] >= $limit ) {
                                                        $mail = $result->buyer['email'];
                                                        wp_mail($mail, 'Identitätscheck Plugin auf '. $_SERVER['HTTP_HOST'], 'Ihr monatliches Prüfungslimit ist erreicht, Bitte führen Sie ein Lizenzupgrade durch.');
														if ( $debug ) {
															var_dump( "Prüfungslimit erschöpft" );
														}
														return true;
													} else {
														$current_checks[ $year ][ $month ] += 1;
														if($limit - $current_checks[$year][$month] <= 10){
                                                            $mail = $result->buyer['email'];
                                                            wp_mail($mail, 'Identitätscheck Plugin auf '. $_SERVER['HTTP_HOST'], 'Ihr monatliches Prüfungslimit ist fast erreicht, Bitte führen Sie ein Lizenzupgrade durch.');
                                                        }
														return false;
													}
												} else {
													$current_checks[ $year ][ $month ] = 1;
													return false;
												}
											} else {
												$current_checks[ $year ][ $month ] = 1;
                                                return false;
											}
										} elseif ( $limit == null ) {
											$current_checks                    = [];
											$current_checks[ $year ][ $month ] = 1;
                                            return false;
										} else {
											$current_checks                    = [];
											$current_checks[ $year ][ $month ] += 1;
                                            return false;
										}
										update_option( 'ID_CHECKS', $current_checks );
										return false;
									} else {
										return false;
									}

								} else {
									return false;
								}
							} else {
								return $this->noSettings();
							}
						}
				} catch ( DigistoreApiException $e ) {
					if ( function_exists( 'wc_add_notice' ) ) {
						wc_add_notice( 'Fehler in der Produktaktivierung', 'error' );
					}
					wp_mail( 'h.baeker@baeker-it.de', 'SCHUFA PLUGIN FEHLER', $e->getMessage() );

					return $this->noSettings();
				} catch (Exception $e)
				{
					if(function_exists('wc_add_notice'))
					{
						wc_add_notice('Fehler in der Produktaktivierung', 'error');
					}
					wp_mail('h.baeker@baeker-it.de', 'SCHUFA PLUGIN FEHLER', $e->getMessage());
				}
			}
		}

		private function characterReplace( $inputvar ) {
			$return              = $inputvar;
			$CharactersToReplace = array(
				'À',
				'Á',
				'Â',
				'Ã',
				'Å',
				'Æ',
				'Ç',
				'È',
				'É',
				'Ê',
				'Ì',
				'Î',
				'Ï',
				'Ð',
				'Ñ',
				'Ò',
				'Ó',
				'Ô',
				'Õ',
				'×',
				'Ø',
				'Ù',
				'Ú',
				'Û',
				'Ý',
				'à',
				'á',
				'â',
				'ã',
				'å',
				'æ',
				'ç',
				'è',
				'é',
				'ê',
				'ë',
				'ì',
				'í',
				'î',
				'ï',
				'ð',
				'ñ',
				'ò',
				'ó',
				'ô',
				'õ',
				'ø',
				'ù',
				'ú',
				'û',
				'ý',
				'ÿ',
				'Ą',
				'Ł',
				'Ľ',
				'Ś',
				'Š',
				'Ş',
				'Ť',
				'Ź',
				'Ž',
				'Ż',
				'ą',
				'ł',
				'ľ',
				'ś',
				'š',
				'ş',
				'ť',
				'ź',
				'ž',
				'ż',
				'Ŕ',
				'Á',
				'Â',
				'Ă',
				'Ĺ',
				'Ć',
				'Ç',
				'Č',
				'É',
				'Ę',
				'Ë',
				'Ě',
				'Í',
				'Î',
				'Ď',
				'Đ',
				'Ń',
				'Ň',
				'Ř',
				'Ů',
				'Ô',
				'Ő',
				'ř',
				'đ',
				'ů',
				'ę',
				'ấ'
			);
			$ReplaceWith         = array(
				'A',
				'A',
				'A',
				'A',
				'A',
				'A',
				'C',
				'E',
				'E',
				'E',
				'I',
				'I',
				'I',
				'D',
				'N',
				'O',
				'O',
				'O',
				'O',
				'X',
				'OE',
				'U',
				'U',
				'U',
				'Y',
				'a',
				'a',
				'a',
				'a',
				'o',
				'a',
				'c',
				'e',
				'e',
				'e',
				'e',
				'i',
				'i',
				'i',
				'i',
				'd',
				'n',
				'o',
				'o',
				'o',
				'o',
				'oe',
				'u',
				'u',
				'u',
				'y',
				'y',
				'A',
				'L',
				'L',
				'S',
				'S',
				'S',
				'T',
				'Z',
				'Z',
				'Z',
				'a',
				'l',
				'l',
				's',
				's',
				's',
				't',
				'z',
				'z',
				'z',
				'R',
				'A',
				'A',
				'A',
				'L',
				'C',
				'C',
				'C',
				'E',
				'E',
				'E',
				'E',
				'I',
				'I',
				'D',
				'D',
				'N',
				'N',
				'R',
				'U',
				'O',
				'O',
				'r',
				'd',
				'u',
				'r',
				'a'
			);
			$return              = str_replace( $CharactersToReplace, $ReplaceWith, $return );

			return $return;
		}

		public
		function checkTestResult(
			$result
		) {
			if ( $result->result ) {
				include plugin_dir_path( __FILE__ ) . 'resources/views/admin/results/positive_table_row.php';
			} else {
				include plugin_dir_path( __FILE__ ) . 'resources/views/admin/results/negative_table_row.php';
			}
		}

		public
		function getCustomerStatus(
			$result
		) {
			if ( ! $result->result ) {
				include plugin_dir_path( __FILE__ ) . 'resources/views/admin/results/negative_head.php';
			} else {
				include plugin_dir_path( __FILE__ ) . 'resources/views/admin/results/positive_head.php';
			}
		}

		private
		function customerHasBeenCheckedWithAddress() {
			global $wpdb;
			$query  = 'SELECT * FROM ' . $wpdb->prefix . 'identitycheck_requests WHERE
			first_name = "' . $this->customer['first_name'] . '" and last_name = "' . $this->customer['last_name'] . '" and
			birthdate = "' . $this->customer['birthdate'] . '" and street = "' . $this->customer['street'] . '" and
			zipcode = "' . $this->customer['zipcode'] . '" and city = "' . $this->customer['city'] . '"
			';
			$result = $wpdb->get_results( $query );
			if ( count( $result ) != 0 ) {
				foreach ( $result as $entry ) {
					if ( $entry->result ) {
						return $entry->result;
					}
				}

				return 2;
			}

			return false;
		}

		public
		function checkCustomer(
			$debug = false, $file = false
		) {
			if ( ! $debug ) {
				if ( $this->countChecks( $debug ) ) {
					if ( function_exists( 'wc_add_notice' ) ) {
						wc_add_notice( 'Der Identitätscheck ist im Augenblick leider nicht verfügbar', 'error' );
					}
					if ( $debug ) {
						var_dump( "Keine Prüfung möglich" );
					}

					return false;
				}
			}
			if ( $debug ) {
				echo "<pre>";
			}
			$handle = xml_parser_create();
			if ( ! $debug ) {
				xml_parse_into_struct( $handle, $this->result, $values );
			} else {
				xml_parse_into_struct( $handle, file_get_contents( plugin_dir_path( __FILE__ ) . '/resources/tests/' . $file ), $values );
			}
			foreach ( array_reverse( $values ) as $key => $value ) {
				if ( array_key_exists( $value['tag'], self::RESPONSE_FIELDS ) or in_array( $value['tag'], self::RESPONSE_FIELDS ) ) {
					if ( array_key_exists( 'value', $value ) or array_key_exists( 'attributes', $value ) ) {
						$result[ $value['tag'] ] = $value;
					}
				}
			}
			if ( isset( $result ) ) {
				foreach ( self::ERRORS as $error ) {
					if ( array_key_exists( $error, $result ) ) {
						$this->error = true;
						if ( function_exists( 'wc_add_notice' ) ) {
							wc_add_notice( $result['ERRORMESSAGE']['value'], 'error' );
						}
						$message = file_get_contents( plugin_dir_path( __FILE__ ) . 'resources/views/mail/error.phtml' );
						$message = str_replace( [ '[ERRORCODE]', '[ERRORMESSAGE]' ], [
							$result['ERROR']['value'],
							$result['ERRORMESSAGE']['value']
						], $message );
						wp_mail( 'support@baeker-it.de', 'Identitätscheck Plugin Fehlermeldung', $message );
						if ( $debug ) {
							var_dump( "FEHLER: " . $result['ERRORMESSAGE']['value'] );
						}
					}
				}
				if ( ! $this->error ) {
					foreach ( self::RESPONSE_FIELDS as $field => $database_field ) {
						if ( array_key_exists( $field, $result ) ) {
							if ( function_exists( 'get_option' ) ) {
								$max = get_option( 'ID_CHECK_' . strtoupper( $database_field ) . '_MAX', 85.0 );
							} else {
								$max = 85.0;
							}
							$current = (double) $result[ $field ]['attributes']['EINZELTREFFERGUETE'];
							if ( $debug ) {
								var_dump( $field . ' Ergebnis: ' . $current . ' Grenzwert:' . $max );
							}
							if ( $current < $max ) {
								if ( function_exists( 'wc_add_notice' ) ) {
									wc_add_notice( $field . ' bitte prüfen', 'error' );
								}
								$this->error  = true;
								$this->passed = false;
								if ( $debug ) {
									var_dump( "NICHT BESTANDEN" );
								}
							} else {
								if ( $debug ) {
									var_dump( "BESTANDEN" );
								}
							}
							$this->customer[ $database_field ] = $result[ $field ]['value'];
						}
					}

					if ( array_key_exists( 'VERBRAUCHERDATEN', $result ) ) {
						$current = (double) $result['VERBRAUCHERDATEN']['attributes']['GESAMTTREFFERGUETE'];
						if ( function_exists( 'get_option' ) ) {
							$max = get_option( 'ID_CHECK_OVERALL_MAX', 85.0 );
						} else {
							$max = 85.0;
						}
						if ( $debug ) {
							var_dump( "Der GESAMTWERT DER PRÜFUNG : " . $current . ' GRENZWERT: ' . $max );
						}
						if ( $current < $max ) {
							if ( function_exists( 'wc_add_notice' ) ) {
								wc_add_notice( 'Die Gesamttrefferguete ist zu gering', 'error' );
								if ( $debug ) {
									var_dump( "Gesamttrefferguete von " . $current . ' NICHT BESTANDEN' );
								}
							}
						} else {
							if ( $debug ) {
								var_dump( "Gesamttrefferguete von " . $current . ' BESTANDEN' );
							}
							$this->passed = true;
						}
					}

					if ( array_key_exists( 'AUSWEISGEPRUEFTEIDENTITAET', $result ) ) {
						if ( strtolower( $result['AUSWEISGEPRUEFTEIDENTITAET']['value'] ) == "nein" ) {
							switch ( get_option( 'ID_CHECK_QBIT' ) ) {
								case true:
									$this->passed = false;
									break;
							}
						}
					}
				}
				var_dump( $this->customer );
				if ( ! $this->customerHasBeenCheckedWithAddress() ) {
					if ( $debug ) {
						var_dump( "Der Kunde existiert noch nicht in der Datenbank" );
					}
					self::saveCheckInDB( $this->passed, $this->customer );
				} else {
					if ( $debug ) {
						var_dump( "Der Kunde existiert bereits in der Datenbank" );
					}
				}
				if ( $debug ) {
					if ( ! $this->passed ) {
						var_dump( "Prüfung NICHT bestanden" );
					} else {
						var_dump( "Prüfung bestanden" );
					}
				}
				if ( $debug ) {
					echo "</pre>";
				}

				return $this->passed;
			} else {
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( 'Bei der Prüfung ist uns ein Fehler unterlaufen', 'error' );
				}
			}

		}

		private
		static function saveCheckInDB(
			$passed = false, $customer
		) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'identitycheck_requests';

			$charset_collate = $wpdb->get_charset_collate();
			$wpdb->insert( $table_name, [
				'time'       => current_time( 'mysql' ),
				'user_id'    => get_current_user_id(),
				'result'     => $passed,
				'first_name' => $customer['first_name'],
				'last_name'  => $customer['last_name'],
				'street'     => $customer['street'],
				'zipcode'    => $customer['zipcode'],
				'city'       => $customer['city'],
				'birthdate'  => $customer['birthdate']
			] );
		}

		private
		function collectUserData() {
			foreach ( self::$field_relations as $post => $field ) {
				if ( filter_input( INPUT_POST, $post ) != null ) {
					$this->customer[ $field ] = filter_input( INPUT_POST, $post );
				}
			}

			foreach ( self::$field_relations as $key => $value ) {
				$this->customer[ $key ] = substr( $this->characterReplace( $value ), 0, self::MAX_CHARS[ strtoupper( $key ) ] );
			}
			self::$replaces_base =
				[
					date( 'Y-m-d H:i:s', time() ),
					get_option( 'ID_CHECK_USER', '000/00000' ),
					get_option( 'ID_CHECK_PASSWORD', false ),
					'Premium',
					$this->customer['first_name'],
					$this->customer['last_name'],
					$this->customer['birthdate'],
					$this->customer['street'],
					$this->customer['zipcode'],
					$this->customer['city']
				];

			return true;
		}

		private
		function initCurl() {
			$this->curl = curl_init();
			curl_setopt( $this->curl, CURLOPT_SSL_VERIFYPEER, 0 );
			curl_setopt( $this->curl, CURLOPT_SSL_VERIFYHOST, 0 );
			curl_setopt( $this->curl, CURLOPT_URL, $this->url );
			curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $this->curl, CURLOPT_TIMEOUT, 10 );
			curl_setopt( $this->curl, CURLOPT_POST, true );
			curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $this->prepareXML() );
		}

		public
		function prepareXML() {
			openssl_pkcs12_read( file_get_contents( plugin_dir_path( __FILE__ ) . 'resources/KeyStore.p12' ), $keystore, '' );
			$res           = openssl_x509_parse( openssl_x509_read( $keystore['cert'] ) );
			$base          = file_get_contents( plugin_dir_path( __FILE__ ) . 'resources/xml/basexml_top.xml' );
			$base_bottom   = file_get_contents( plugin_dir_path( __FILE__ ) . 'resources/xml/basexml_bottom.xml' );
			$security      = file_get_contents( plugin_dir_path( __FILE__ ) . 'resources/xml/security.xml' );
			$signature     = file_get_contents( plugin_dir_path( __FILE__ ) . 'resources/xml/signature.xml' );
			$base_bottom   = str_replace( self::$replacements_base, self::$replaces_base, $base_bottom );
			$base_complete = $base . $base_bottom;
			$security      = str_replace( '[DIGEST]', self::generateDigestValue( $base_complete ), $security );
			$security      .= self::generateSignatureValue( $security, $keystore['pkey'] );
			$signature     = str_replace( '[SIGNATURE]', $security, $signature );
			$xml           = '<?xml version="1.0" encoding="UTF-8" ?>' . $base . $signature . $base_bottom;
            file_put_contents(plugin_dir_path(__FILE__).'debugRequest.xml', $xml);
			return $xml;
		}

		private
		static function generateSignatureValue(
			$xml, $key
		) {
			$return = new DOMDocument();
			$return->loadXML( $xml );
			$sign = $return->C14N( true, true );
			if ( openssl_sign( $sign, $signed, $key ) ) {
				return "<SignatureValue>" . base64_encode( $signed ) . "</SignatureValue>";
			} else {
				return 'Fehler beim Signieren';
			}
		}

		private
		static function generateDigestValue(
			$xml
		) {
			$return = new DOMDocument();
			$return->loadXML( $xml );
			$return = $return->C14N( true, true );
			$return = sha1( $return );
			$return = hex2bin( $return );

			return base64_encode( $return );
		}

		public
		function order_fields(
			$fields
		) {
			$order = array(
				"billing_title",
				"billing_first_name",
				"billing_last_name",
				"billing_company",
				"billing_birthdate",
				"billing_address_1",
				"billing_address_2",
				"billing_postcode",
				"billing_city",
				"billing_country",
				"billing_email",
				"billing_phone"

			);
			foreach ( $order as $field ) {
				@$ordered_fields[ $field ] = $fields["billing"][ $field ];
			}
			$fields["billing"]                                     = $ordered_fields;
			$fields["billing"]['billing_birthdate']['label']       = __( 'Geburtsdatum (TT.MM.JJJJ): ' );
			$fields["billing"]['billing_birthdate']['label_class'] = array( 'billing-birthdate' );
			$fields["billing"]['billing_birthdate']['required']    = true;
			$fields["billing"]['billing_birthdate']['class']       = array( 'input-text billing-birthdate' );
			$fields["billing"]['billing_birthdate']['type']        = 'text';

			return $fields;

		}

		public
		function show_SCHUFA_Status(
			$user, $return = false
		) {
			$status = get_user_meta( $user->ID, 'SCHUFA_CHECK_STATUS', true );
			include plugin_dir_path( __FILE__ ) . 'resources/views/admin/user/status.php';
		}

		public
		function menuSetup() {
			add_menu_page( 'Schufa Identitätscheck', 'Schufa Identitätscheck', 'edit_posts', 'schufa_identity_check', [
				$this,
				'show_settings'
			], null, 3 );
			add_submenu_page( 'schufa_identity_check', 'Plugin Einstellungen', 'Plugin Einstellungen', 'manage_options', 'plugin-einstellungen', [
				$this,
				'schufa_identity_check_settings'
			] );
			add_submenu_page( 'schufa_identity_check', 'Support', 'Support', 'manage_options', 'support', [
				$this,
				'supportPage'
			] );
			add_submenu_page( 'schufa_identity_check', 'Bisherige Prüfungen', 'Ergebnisse', 'manage_options', 'results', [
				$this,
				'showResults'
			] );
		}

		public
		function showResults() {
			include plugin_dir_path( __FILE__ ) . 'resources/views/admin/results/results.php';
		}

		public
		function supportPage() {
			include plugin_dir_path( __FILE__ ) . 'resources/views/admin/support/index.php';
		}

		public
		function show_settings() {
			include plugin_dir_path( __FILE__ ) . 'resources/views/admin/config/overview.php';
		}


		public
		function datenuebergabeschufa(
			$checkout
		) {

			echo '<div id="additional-checkboxes"><h3>' . __( 'Altersverifikation: ' ) . '</h3>';

			woocommerce_form_field( 'schufa_checkbox', array(
				'type'        => 'checkbox',
				'class'       => array( 'input-checkbox' ),
				'label'       => __( 'Ich willige ein, dass meine persönlichen Daten zum Zweck der Altersprüfung an die SCHUFA Holding AG (Kormoranweg 5, 65201 Wiesbaden) übermittelt werden. Eine Speicherung meiner Daten im SCHUFA-Datenbestand oder ein weiterer Datenaustausch findet nicht statt. Nur das Ergebnis der Prüfung meines Alters wird bei der SCHUFA gespeichert. Eine Bonitätsprüfung erfolgt ausdrücklich nicht! Nähere Informationen finden Sie unter <a href="http://www.meineschufa.de" target="_blank">www.meineschufa.de</a>' ),
				'label_class' => array( 'schufa_identity_check_agreement' ),
				'required'    => true,
			) );

			echo '</div>';
		}


		public
		function datenubergabeschufa_process() {

			// Check if set, if its not set add an error.
			if ( $_POST['schufa_checkbox'] != 1 ) {
				wc_add_notice( '<strong>Bitte stimmen Sie der Identitätsprüfung durch die SCHUFA zu</strong> ', 'error' );
			}
		}


		public
		function datenuebergabeschufa_update_order_meta(
			$order_id
		) {
			if ( $_POST['schufa_checkbox'] ) {
				update_post_meta( $order_id, 'Altersverifikation', esc_attr( $_POST['schufa_checkbox'] ) );
			}
		}

		private static function customerIsGerman(){
		    return (strtolower(filter_input(INPUT_POST, 'billing_country')) == 'de') ? true : false;
        }

		public
		function schufaPruefung() {
		    if(self::customerIsGerman()) {
                if (get_user_meta(get_current_user_id(), 'ID_CHECK', true) != 1) {
                    if (get_user_meta(get_current_user_id(), 'ID_CHECK_IN_PROGRESS', true) != true) {
                        $this->setUserStatusInCheck(true);
                        $result = $this->initCheck();
                        $this->setUserStatusInCheck(false);

                        return $result;
                    }
                }
            }
		}

		private
		function setUserStatusInCheck(
			$status
		) {
			update_user_meta( get_current_user_id(), 'SCHUFA_CHECK_IN_PROGRESS', $status );
		}

		public
		function schufa_identity_check_settings() {
			include plugin_dir_path( __FILE__ ) . 'resources/views/admin/config/configuration.php';
		}


		public
		function schufa_age_verify_init() {
			register_setting( 'schufa_settings', 'schufa_setting', 'schufa_validate' );
		}

		public
		function schufa_validate(
			$input
		) {
			return $input;
		}

		public
		function SchufaFallbackMenu() {
			add_menu_page( 'Schufa Identitätscheck', 'Schufa Identitätscheck', 'edit_posts', 'schufa_identity_check', 'schufa_fallback', null, 3 );
		}

		public
		function schufa_fallback() {
			include plugin_dir_path( __FILE__ ) . 'resources/views/admin/config/configuration_error.php';
		}

	}
}

add_action( 'init', 'initSCHUFAIDCheckPlugin' );

function initSCHUFAIDCheckPlugin() {
	$SCHUFA_IDCheck = new SCHUFA_IDCheck();
}