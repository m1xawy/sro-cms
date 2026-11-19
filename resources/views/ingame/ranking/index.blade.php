<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="robots" content="index, follow" />
<meta name="keywords" content="silkroad, Silkroad Online, MMORPG, Free to play, GameGami, online oyun, oyun, silk, gold, SRO, Bot, f2p, hardcore mmorpg, Online game, free online mmorpg, Free game, joymax, pc game, free download, download" />
<meta name="Description" content="Silkroad Online dünyanın en çok oynanan ücretsiz MMORPG oyunlarının başında gelmektedir. Silkroad Online'da eski Çin, İslam ve Avrupa medeniyetlerinin derinliklerine gidecek ve PvP, zindan sistemleri, sonsuz kale savaşları ile en iyi kahramanlardan biri olmak için çarpışacaksınız!" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>{{ config('app.name') }} - Official Ranking</title>
<link href="{{ asset('ingame/ranking/images/favicon.png') }}" rel="shortcut icon" type="image/vnd.microsoft.icon" />
<style>
	body,html {
		font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
		color: #ffffff;
		margin: 0;
		padding: 0;
		overflow: hidden;
		background-color:#FF0000;
		background: url('{{ asset('ingame/ranking/images/bg.png') }}') 0 0 repeat;
	}
	#rankmain {
		width: 800px;
		height: 569px;
		background: url('{{ asset('ingame/ranking/images/rankbg.png') }}') 0 0 no-repeat;
		position: absolute;
		top: 0;
		left: 0;
		overflow: hidden;
	}

	.table_rank {
		margin: 0 0 0 2px;
		width: 644px;
		border-top: 1px solid #564e41;
		border-left: 1px solid #564e41;
		border-right: 1px solid #564e41;
		min-height: 466px;
	}
	.table_rank th {
		background-color: #211c17;
		font-size: 12px;

		color: #e8cf9a;
		border-bottom: 1px solid #564e41;
	}
	.table_rank .th1 { /* icon */
		width: 65px;
		height: 24px;
	}
	.table_rank .th2 { /* sıralama */
		width: 30px;
		height: 24px;
	}
	.table_rank .th3 { /* ırk */
		width: 40px;
		height: 24px;
	}
	.table_rank .th4 { /* karakter adı */
		width: 330px;
		height: 24px;
		text-align: left;
		padding-left: 10px;
	}
	.table_rank .th5 { /* puan */
		width: 100px;
		height: 24px;
	}
	.table_rank .th6 { /* sıra değişimi */
		width: 77px;
		height: 24px;
	}
	.table_rank td {
		/*background-color: #000000;*/
		color: #8a834e;
		font-size: 12px;
		border-bottom: 1px solid #564e41;
	}
	.table_rank .td1 {
		width: 65px;
		height: 21px;
		text-align: right;
	}
	.table_rank .td2 {
		width: 30px;
		height: 21px;
		text-align: center;
	}
	.table_rank .td3 {
		width: 40px;
		height: 21px;
		text-align: right;
	}
	.table_rank .td4 {
		width: 330px;
		height: 21px;
		text-align: left;
		padding-left: 10px;
	}
	.table_rank .td5 {
		width: 100px;
		height: 21px;
		text-align: center;
	}
	.table_rank .td6 {
		width: 77px;
		height: 21px;
		text-align: center;
	}

	#button_website {
		width: 133px;
		height: 30px;
		text-align: center;
		color: #a9985d;
		line-height: 30px;
		font-size: 12px;
		cursor: pointer;
		margin: 15px auto 0 auto;
		background: url('{{ asset('ingame/ranking/images/button_webpage.png') }}') 0 0 no-repeat;
	}
	#button_website:hover {
		color: #d7c791;
	}

	.menu_label {
		width: 800px;
		height: 44px;
		background: url('{{ asset('ingame/ranking/images/menubg.png') }}') 0 0 no-repeat;
		margin: 5px auto 0 auto;
		color: #e8cf9a;
		font-weight: bold;
		font-size: 12px;
	}
	.menu_label td {
		padding: 0;
		margin: 0;
	}
	.menu_label .label1 {
		font-size: 18px;
		width: 148px;
		text-align: center;
	}
	.menu_label .label2 {
		width: 24px;
		text-align: left;
		padding-left: 79px;
	}
	.menu_label .label3 {
		width: 345px;
		text-align: left;
		padding-left: 16px;
	}
	.menu_label .label4 {
		width: 100px;
		text-align: center;
	}
	.menu_label .label5 {
		text-align: center;
	}


	#rankmenu2_container {
		width: 148px;
		height: 466px;
		margin: 0;
	}

	#rankmenu2_container #rankmenu_label {
		width: 100px;
		height: 36px;
		float: left;
		font-size: 16px;
		color: #c2e1a4;
		margin-top: 4px;
		text-align: center;
		line-height: 36px;
	}
	#rankmenu2_container ul {
		list-style-type: none;
		padding: 0;
		margin: 0;
		float: left;

	}

	#rankmenu2_container ul li {
		display: block;
		width: 148px;
		height: 32px;
		background: url('{{ asset('ingame/ranking/images/button_nonselected_v2.png') }}') 0 0 no-repeat;
	}
	#rankmenu2_container ul li.selected {
		background: url('{{ asset('ingame/ranking/images/button_selected_v2.png') }}') 0 0 no-repeat;
	}
	#rankmenu2_container ul li.selected a {
		color: #c2e1a4;
	}
	#rankmenu2_container ul li a {
		display: inline-block;
		color: #8c7c63;
		text-decoration: none;
		font-size: 12px;
		width: 148px;
		height: 32px;
		line-height: 32px;
		text-align: center;

	}
	#rankmenu2_container ul li a:hover {
		text-decoration: none;
	}

	.table_cover {
		width: 800px;
	}

	.table_cover .td_left_menu {
		height: 466px;
		vertical-align: top;
		width: 148px;
		padding: 0;
		margin: 0;
		background: url('{{ asset('ingame/ranking/images/leftmenu_bg.png') }}') 0 0 no-repeat;
	}
	.table_cover .td_rank {
		height: 441px;
		width: 652px;
		padding: 0;
		margin: 0;
	}
</style>
</head>
<body>
<script type="text/javascript">
function blankurl(val)
{
	 window.open(val, '_blank');
}
</script>

@php
    $internalCols = ['ID', 'CharID', 'GuildID', 'RefObjID'];
    $firstKey = array_key_first($ranking ?: []);
@endphp

<div id="rankmain">
	<table class="menu_label">
		<tr>
			<td class="label1" style="width:800px" id="rankTitle">{{ ($ranking[$firstKey]['name'] ?? 'Ranking') }}</td>
		</tr>
	</table>
	<table class="table_cover">
		<tr>
			<td class="td_left_menu"><div id="rankmenu2_container">
                <ul id="rank-menu">
                    @if(empty($ranking))
                        <li class="selected"><a href="#">Ranking</a></li>
                    @else
                        @foreach($ranking as $key => $item)
                            <li class="{{ $loop->first ? 'selected' : '' }}" onclick="return showRanking(this, {{ $loop->index }});"><a href="#" onclick="return false;">{{ $item['name'] }}</a></li>
                        @endforeach
                    @endif
                </ul>
            </div>
            </td>
			<td class="td_rank" id="rank-panels"> <!-- rank -->
                @forelse($ranking as $key => $item)
                    @php
                        $rows = $data[$key] ?? collect();
                        $first = $rows->first();
                        $cols = $first ? (array) $first : [];
                        $cols = array_values(array_filter(array_keys($cols), fn ($col) => ! in_array($col, $internalCols)));
                    @endphp
                    <div class="rank-panel" style="{{ $loop->first ? '' : 'display:none;' }}">
                        <table class="table_rank" cellpadding="0" cellspacing="0">
                            <tr>
                                <th class="th1">&nbsp;</th>
                                <th class="th2">#</th>
                                <th class="th3">&nbsp;</th>
                                @foreach($cols as $ci => $col)
                                    <th class="{{ $ci < 3 ? 'th'.($ci + 4) : 'th6' }}">{{ $col }}</th>
                                @endforeach
                                <th class="th6">Change</th>
                            </tr>
                            @if($rows->isNotEmpty())
                                @foreach($rows as $ri => $row)
                                    <tr onMouseOver='this.style.background="#2e261e"' onMouseOut='this.style.background="none"'>
                                        <td class="td1">
                                            @if($ri < 3 && isset($topImage[$ri + 1]))
                                                <img src="{{ asset('ingame/ranking/' . $topImage[$ri + 1]) }}" style="vertical-align:text-top" />
                                            @endif
                                        </td>
                                        <td class="td2">{{ $ri + 1 }}</td>
                                        <td class="td3">
                                            @if(isset($row->RefObjID))
                                                <img src="{{ asset('ingame/ranking/images/' . ($row->RefObjID > 2000 ? 'european' : 'chinese') . '.png') }}" style="vertical-align:text-top" />
                                            @endif
                                        </td>
                                        @foreach($cols as $ci => $col)
                                            <td class="{{ $ci < 3 ? 'td'.($ci + 4) : 'td6' }}">
                                                {{ $row->$col }}
                                            </td>
                                        @endforeach
                                        <td class="td6"><center><img style="width: 16px; height: 16px;" src="{{ asset('ingame/ranking/images/nochange.png') }}" title="Değişiklik yok"></center></td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="{{ count($cols) + 4 }}" style="text-align:center;">
                                        {{ ! empty($errors[$key]) ? 'Error loading data.' : 'No data to display.' }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                @empty
                    <div class="rank-panel">
                        <table class="table_rank" cellpadding="0" cellspacing="0">
                            <tr><td colspan="4" style="text-align:center;">No ranking configured.</td></tr>
                        </table>
                    </div>
                @endforelse
			</td>
		</tr>
	</table>
<div id="button_website" onclick="blankurl('{{ config('app.url') }}')">Official Website</div>
</div>
<script type="text/javascript">
// IE7-safe tab switching (no addEventListener / querySelector / classList).
function showRanking(li, index) {
    var menu = document.getElementById('rank-menu');
    if (menu) {
        var items = menu.getElementsByTagName('li');
        for (var i = 0; i < items.length; i++) {
            items[i].className = '';
        }
    }
    li.className = 'selected';

    var title = document.getElementById('rankTitle');
    var link = li.getElementsByTagName('a')[0];
    if (title && link) {
        title.innerHTML = link.innerHTML;
    }

    var container = document.getElementById('rank-panels');
    if (!container) {
        return false;
    }

    var panels = container.getElementsByTagName('div');
    var panelIndex = 0;
    for (var j = 0; j < panels.length; j++) {
        if (panels[j].className.indexOf('rank-panel') === -1) {
            continue;
        }
        panels[j].style.display = (panelIndex === index) ? '' : 'none';
        panelIndex++;
    }

    return false;
}
</script>

</body>
</html>
