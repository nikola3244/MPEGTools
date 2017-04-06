<?php

class mp3 {

	const MP3_CHANNEL_STEREO = 0;
	const MP3_CHANNEL_JOINT_STEREO = 1;
	const MP3_CHANNEL_DUAL_MONO = 2;
	const MP3_CHANNEL_MONO = 3;

	/**
	 * The mp3 file converted to binary
	 *
	 * @var null|string
	 */

	public $file = null;

	/**
	 * The URL to the mp3 file
	 *
	 * @var null|string
	 */

	public $url = null;

	/**
	 * mp3 constructor.
	 *
	 * @param string $url The url to the file
	 */

	public function __construct( $url ) {
		$this->url = $url;
	}

	/**
	 * Gets header data from the mp3 file
	 *
	 * @return array
	 */

	public function getHeaderData() {
		$possibleHeaders = $this->getPotentialHeaders();
		foreach ( $possibleHeaders as $key => &$possibleHeader ) {
			$possibleHeader = array(
				'MPEG_VERSION'      => $this->mp3MPEGAudioVersionID( $possibleHeader ),
				'LAYER_DESCRIPTION' => $this->mp3LayerDescription( $possibleHeader ),
				'PROTECTION'        => $this->mp3Protection( $possibleHeader ),
				'BITRATE'           => $this->mp3Bitrate( $possibleHeader ),
				'SAMPLING_RATE'     => $this->mp3SamplingRate( $possibleHeader ),
				'PADDING'           => $this->mp3Padding( $possibleHeader ),
				'CHANNEL_MODE'      => $this->mp3ChannelMode( $possibleHeader ),
			);

			foreach ( $possibleHeader as $value ) {
				if ( $value === null ) {
					unset( $possibleHeaders[ $key ] );
					break;
				}
			}
		}

		return $possibleHeaders;
	}

	/**
	 * Get all headers by searching for frame sync
	 *
	 * @return array Potential headers
	 */

	public function getPotentialHeaders() {
		$file        = $this->getFile();
		$length      = strlen( $file );
		$last_header = 0;
		$headers     = array();

		while ( $last_header !== false && $length > $last_header + 26 ) {
			$last_header     = strpos( $file, '11111111111', $last_header + 26 );
			$possible_header = substr( $file, $last_header, 26 );
			if ( substr( $possible_header, 11, 2 ) === '10' || substr( $possible_header, 11, 2 ) === '11' ) {
				$headers[] = substr( $file, $last_header, 26 );
			}
		}

		return $headers;
	}

	/**
	 * Gets the current file or gets a fresh one if current is null
	 *
	 * @throws Exception
	 *
	 * @return mixed
	 */

	public function getFile() {
		if ( $this->file === null ) {
			if ( $this->openFile() ) {
				return $this->file;
			} else {
				throw new Exception( 'Failed to get the file.' );
			}
		}
	}

	/**
	 * Opens the file and reads first 8KB
	 *
	 * @return true True on success
	 */

	public function openFile() {
		$fp = fopen( $this->url, 'r', false );

		if ( $fp === false ) {
			return false;
		}

		$this->file = $this->convertToBinary( fread( $fp, 8192 ) );

		fclose( $fp );

		return true;
	}

	/**
	 * Converts ASCII file to binary
	 *
	 * @param string $file String to convert
	 *
	 * @return string
	 */

	public function convertToBinary( $file ) {
		$binary = '';

		// iterate through each byte in the contents
		for ( $i = 0; $i < 8192; $i ++ ) {
			// get the current ASCII character representation of the current byte
			$asciiCharacter = $file[ $i ];
			// get the base 10 value of the current character
			$base10value = ord( $asciiCharacter );
			// now convert that byte from base 10 to base 2 (i.e 01001010...)
			$base2representation = base_convert( $base10value, 10, 2 );
			// print the 0s and 1s
			$binary .= $base2representation;
		}

		return $binary;
	}

	/**
	 * Gets MPEG version ID
	 *
	 * @param string $header 24 bit mp3 header
	 *
	 * @return null|int|float Null if the header invalid
	 */

	public function mp3MPEGAudioVersionID( $header ) {
		$id = substr( $header, 11, 2 );

		switch ( $id ) {
			case '00':
				return 2.5;
			case '01':
				return null;
			case '10':
				return 2;
			case '11':
				return 1;
		}
	}

	/**
	 * Gets MP3 Layer
	 *
	 * @param string $header 24 bit mp3 header. 1 for Layer I, 2 for Layer II, 3 for Layer III
	 *
	 * @return null|int Null if it's invalid
	 */

	public function mp3LayerDescription( $header ) {
		$id = substr( $header, 13, 2 );

		switch ( $id ) {
			case '00':
				return null;
			case '01':
				return 3;
			case '10':
				return 2;
			case '11':
				return 1;
		}
	}

	/**
	 * Reads if the mp3 file uses CRC protection
	 *
	 * @param string $header 24 bit mp3 header
	 *
	 * @return bool True if it does, false otherwise
	 */

	public function mp3Protection( $header ) {
		$protection = substr( $header, 15, 1 );

		return boolval( $protection );
	}

	/**
	 * Gets the mp3 bit rate
	 *
	 * @param string $header 24 bit mp3 header
	 *
	 * @return int|true|null Integer bitrate; True if it's free; Null if it's invalid
	 */

	public function mp3Bitrate( $header ) {
		$bitrate = substr( $header, 16, 4 );
		$version = $this->mp3MPEGAudioVersionID( $header );
		$layer   = $this->mp3LayerDescription( $header );
		$rates   = null;

		if ( $version === 1 ) {
			if ( $layer === 1 ) {
				$rates = array(
					'0000' => true,
					'0001' => 32,
					'0010' => 64,
					'0011' => 96,
					'0100' => 128,
					'0101' => 160,
					'0110' => 192,
					'0111' => 224,
					'1000' => 256,
					'1001' => 288,
					'1010' => 320,
					'1011' => 352,
					'1100' => 384,
					'1101' => 416,
					'1110' => 448,
					'1111' => null
				);
			} elseif ( $layer === 2 ) {
				$rates = array(
					'0000' => true,
					'0001' => 32,
					'0010' => 48,
					'0011' => 56,
					'0100' => 64,
					'0101' => 80,
					'0110' => 96,
					'0111' => 112,
					'1000' => 128,
					'1001' => 160,
					'1010' => 192,
					'1011' => 224,
					'1100' => 256,
					'1101' => 320,
					'1110' => 384,
					'1111' => null
				);
			} elseif ( $layer === 3 ) {
				$rates = array(
					'0000' => true,
					'0001' => 32,
					'0010' => 40,
					'0011' => 48,
					'0100' => 56,
					'0101' => 64,
					'0110' => 80,
					'0111' => 96,
					'1000' => 112,
					'1001' => 128,
					'1010' => 160,
					'1011' => 192,
					'1100' => 224,
					'1101' => 256,
					'1110' => 320,
					'1111' => null
				);
			}
		} elseif ( $version === 2 ) {
			if ( $layer === 1 ) {
				$rates = array(
					'0000' => true,
					'0001' => 32,
					'0010' => 48,
					'0011' => 56,
					'0100' => 64,
					'0101' => 80,
					'0110' => 96,
					'0111' => 112,
					'1000' => 128,
					'1001' => 144,
					'1010' => 160,
					'1011' => 176,
					'1100' => 192,
					'1101' => 224,
					'1110' => 256,
					'1111' => null
				);
			} elseif ( $layer !== false ) {
				$rates = array(
					'0000' => true,
					'0001' => 8,
					'0010' => 16,
					'0011' => 24,
					'0100' => 32,
					'0101' => 40,
					'0110' => 48,
					'0111' => 56,
					'1000' => 64,
					'1001' => 80,
					'1010' => 96,
					'1011' => 112,
					'1100' => 128,
					'1101' => 144,
					'1110' => 160,
					'1111' => null
				);
			}
		}

		if ( $rates === null ) {
			return null;
		}

		// there are some combinations that are not allowed on Layer II
		if ( $layer === 2 ) {
			switch ( $rates[ $bitrate ] ) {
				case 32:
				case 48:
				case 56:
				case 80:
					if ( $this->mp3ChannelMode( $header ) !== self::MP3_CHANNEL_MONO ) {
						return null;
					}
					break;
				case 224:
				case 256:
				case 320:
				case 384:
					if ( $this->mp3ChannelMode( $header ) === self::MP3_CHANNEL_MONO ) {
						return null;
					}
					break;
			}
		}

		return $rates[ $bitrate ];
	}

	/**
	 *
	 *
	 * @param string $header 24 bit mp3 header
	 *
	 * @return int|null Null if it's invalid
	 */

	public function mp3ChannelMode( $header ) {
		$channel_mode = substr( $header, 24, 2 );

		switch ( $channel_mode ) {
			case '00':
				return self::MP3_CHANNEL_STEREO;
			case '01':
				return self::MP3_CHANNEL_JOINT_STEREO;
			case '10':
				return self::MP3_CHANNEL_DUAL_MONO;
			case '11':
				return self::MP3_CHANNEL_MONO;
		}

		return null;
	}

	/**
	 * Get MP3 Sampling Rate (in HZ)
	 *
	 * @param string $header 24 bit mp3 header
	 *
	 * @return int|null Integer sampling rate; Null if it's invalid
	 */

	public function mp3SamplingRate( $header ) {
		$sampling_rate = substr( $header, 20, 2 );
		$version       = $this->mp3MPEGAudioVersionID( $header );
		$rates         = null;

		if ( $version === 1 ) {
			$rates = array(
				'00' => 44100,
				'01' => 48000,
				'10' => 32000,
				'11' => false,
			);
		} elseif ( $version === 2 ) {
			$rates = array(
				'00' => 22050,
				'01' => 24000,
				'10' => 16000,
				'11' => false,
			);
		} elseif ( $version === 2.5 ) {
			$rates = array(
				'00' => 11025,
				'01' => 12000,
				'10' => 8000,
				'11' => false,
			);
		}

		if ( $rates === null ) {
			return null;
		}

		return $rates[ $sampling_rate ];
	}

	/**
	 * Reads if the mp3 file uses padding
	 *
	 * @param string $header 24 bit mp3 header
	 *
	 * @return bool True if it does, false otherwise
	 */

	public function mp3Padding( $header ) {
		$padding = substr( $header, 22, 1 );

		return boolval( $padding );
	}

	/**
	 * Reads the custom bit
	 *
	 * @param string $header 24 bit mp3 header
	 *
	 * @return bool True if it's set, false otherwise
	 */

	public function mp3Private( $header ) {
		$private = substr( $header, 23, 1 );

		return boolval( $private );
	}
}
