<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width">
	<?php
	if ( function_exists( 'wp_no_robots' ) ) {
		wp_no_robots();
	}
	?>
	<title><?php _e( 'Webmention Endpoint', 'webmention' ); ?></title>
	<style type="text/css">
		html {
			background: #f1f1f1;
		}
		body {
			background: #fff;
			color: #444;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			margin: 2em auto;
			padding: 1em 2em;
			max-width: 700px;
			-webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);
			box-shadow: 0 1px 3px rgba(0,0,0,0.13);
		}
		h1 {
			border-bottom: 1px solid #dadada;
			clear: both;
			color: #666;
			font-size: 24px;
			margin: 30px 0 0 0;
			padding: 0;
			padding-bottom: 7px;
		}
		#webmention-endpint-page {
			margin-top: 50px;
		}
		#webmention-endpint-page p {
			font-size: 14px;
			line-height: 1.5;
			margin: 25px 0 20px;
		}
		#webmention-endpint-page code {
			font-family: Consolas, Monaco, monospace;
		}
		ul li {
			margin-bottom: 10px;
			font-size: 14px ;
		}
		a {
			color: #0073aa;
		}
		a:hover,
		a:active {
			color: #00a0d2;
		}
		a:focus {
			color: #124964;
			-webkit-box-shadow:
				0 0 0 1px #5b9dd9,
				0 0 2px 1px rgba(30, 140, 190, .8);
			box-shadow:
				0 0 0 1px #5b9dd9,
				0 0 2px 1px rgba(30, 140, 190, .8);
			outline: none;
		}
		.button {
			background: #f7f7f7;
			border: 1px solid #ccc;
			color: #555;
			display: inline-block;
			text-decoration: none;
			font-size: 13px;
			line-height: 26px;
			height: 28px;
			margin: 0;
			padding: 0 10px 1px;
			cursor: pointer;
			-webkit-border-radius: 3px;
			-webkit-appearance: none;
			border-radius: 3px;
			white-space: nowrap;
			-webkit-box-sizing: border-box;
			-moz-box-sizing:    border-box;
			box-sizing:         border-box;
			-webkit-box-shadow: 0 1px 0 #ccc;
			box-shadow: 0 1px 0 #ccc;
			vertical-align: top;
		}
		.button.button-large {
			height: 30px;
			line-height: 28px;
			padding: 0 12px 2px;
		}
		.button:hover,
		.button:focus {
			background: #fafafa;
			border-color: #999;
			color: #23282d;
		}
		.button:focus  {
			border-color: #5b9dd9;
			-webkit-box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
			box-shadow: 0 0 3px rgba( 0, 115, 170, .8 );
			outline: none;
		}
		.button:active {
			background: #eee;
			border-color: #999;
			-webkit-box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
			box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
			-webkit-transform: translateY(1px);
			-ms-transform: translateY(1px);
			transform: translateY(1px);
		}
	</style>
	</head>
	<body id="webmention-endpint-page">
		<p><?php echo $data['message']; ?></p>

		<p id="backtoblog"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php
			/* translators: %s: site title */
			printf( esc_html_x( '&larr; Back to %s', 'site', 'webmention' ), get_bloginfo( 'title', 'display' ) );
			?>
		</a></p>
	</body>
</html>
