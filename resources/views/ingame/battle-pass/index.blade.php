<!DOCTYPE html PUBLIC '-//W3C//DTD XHTML 1.0 Transitional//EN' 'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'>

<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
	@php $ver = filemtime(public_path('ingame/battle-pass/script.js')); @endphp
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="Keywords" content="silkroad, silkroadonline, joymax, onlinesilkroad, silkroad-online" />
	<META http-equiv='Page-Enter' content='blendTrans(Duration=0.2)'>
	<META http-equiv='Page-Exit' content='blendTrans(Duration=0.2)'>
	<link rel="stylesheet" type="text/css" media="all" href="{{ asset('ingame/battle-pass/itemmall_game.css') }}?v={{ $ver }}" />
	<link rel="stylesheet" type="text/css" media="all" href="{{ asset('ingame/battle-pass/style.css') }}?v={{ $ver }}" />
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/library/config/jquery-1.4.2.min.js') }}"></script>
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/jquery.jcarousel.min.js') }}"></script>
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/jquery.pngFix.js') }}"></script>
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/jquery.sexy-combo.min.js') }}"></script>
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/jquery.cluetip.js') }}"></script>
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/ingame_shell.js') }}"></script>
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/_common.js') }}"></script>
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/jquery.scroll.js') }}"></script>

	<!-- Battle Pass API Client -->
	<script type="text/javascript" src="{{ asset('ingame/battle-pass/script.js') }}?v={{ $ver }}"></script>

	<title>Silkroad Online - Battle Pass</title>
	<base target="_blank">
</head>
<body class="mig " ondragstart="return false" onselectstart="return false">
<div id="wrap" class="mall-list">
	<div id="header">
		<h1>Battle Pass</h1>
		<ul id="gnb"></ul>
	</div>

	<div id="developer">
		<div id="progress">
			<div id="progress-bar" class="progress-bar" style="width: 0%"></div>
			<span id="progress-text" class="progress-text">0/0</span>
		</div>

		<div id="lead"></div>

		<div id="fol" class="setter">
			<!-- Content -->
			<div id="content">
				<div id="screen">
					<div class="opener mold"></div>
					<div class="cropped">
						<ul id="tier-list" class="list"></ul>
					</div>
					<div class="closer mold"></div>
				</div>

                <div class="pagex" id="pagex"></div>
            </div>
			<!-- //Content -->
		</div>
	</div>
</div>

<div id="alert_modal" class="modal">
	<div class="alert_window">
		<div class="alert_title" id="alert_title"></div>
		<div class="alert_content">
			<div class="contents" id="alert_content"></div>
		</div>
		<div class="alert_footer">
			<button id="alert_ok" class="alert_button" style="display:none">OK</button>
			<button id="alert_close" class="alert_button">Cancel</button>
		</div>
	</div>
</div>
</body>
</html>
