<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Supervisord Monitoring</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	// Versiona os assets pelo mtime: o nginx entrega css/js com cache de 10 anos
	// (expires max), entao a URL precisa mudar quando o arquivo muda.
	$asset = function($path){
		$mtime = @filemtime(FCPATH.$path);
		return base_url($path).(!$mtime ? '' : '?v='.$mtime);
	};
	?>
	<link type="text/css" rel="stylesheet" href="<?php echo $asset('/css/bootstrap.min.css');?>"/>
	<link type="text/css" rel="stylesheet" href="<?php echo $asset('/css/custom.css');?>"/>
<script type="text/javascript" src="<?php echo $asset('/js/jquery-1.10.1.min.js');?>"></script>
<script type="text/javascript" src="<?php echo $asset('/js/bootstrap.min.js');?>"></script>
	<noscript>
	<?php
	if($this->config->item('refresh')){ ?>
	<meta http-equiv="refresh" content="<?php echo $this->config->item('refresh');?>">
	<?php } ?>
	</noscript>
</head>
<body>
	<nav class="navbar navbar-inverse navbar-fixed-top">
		<div class="container-fluid">
			<div class="navbar-header">
				<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-main" aria-expanded="false">
					<span class="sr-only">Toggle navigation</span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
					<span class="icon-bar"></span>
				</button>
				<a class="navbar-brand" href="<?php echo site_url('');?>"><span class="glyphicon glyphicon-dashboard"></span> Support Center</a>
			</div>
			<div class="collapse navbar-collapse" id="navbar-main">
				<ul class="nav navbar-nav">
					<li class="active"><a href="<?php echo site_url();?>"><span class="glyphicon glyphicon-home"></span> Home</a></li>
					<li><a href="?mute=<?php echo ($muted?-1:1);?>"><span class="glyphicon glyphicon-volume-<?php echo ($muted?'up':'off');?>"></span> <?php echo ($muted?'Unmute':'Mute');?></a></li>
				</ul>
				<ul class="nav navbar-nav navbar-right">
					<li><a href="<?php echo site_url();?>" title="Auto refresh"><span class="glyphicon glyphicon-refresh"></span> <span class="badge" id="refresh"><?php echo $this->config->item('refresh');?></span></a></li>
					<li><a href="mailto:cpdrenato@gmail.comphicon glyphicon-envelope"></span> Contact</a></li>
				</ul>
			</div>
		</div>
	</nav>

	<?php
	// Resumo geral e "saude" de cada servidor, calculados antes da renderizacao.
	$summary = array('RUNNING'=>0,'STOPPED'=>0,'FATAL'=>0,'EXITED'=>0,'OTHER'=>0);
	$server_health = array();
	$server_count = array();
	$total_procs = 0;
	foreach($list as $name=>$procs){
		$server_count[$name] = 0;
		if(isset($procs['error'])){
			$server_health[$name] = 'danger';
			continue;
		}
		$health = 'success';
		foreach($procs as $item){
			if(!is_array($item)){
				$health = 'danger';
				continue;
			}
			$state = isset($item['statename']) ? $item['statename'] : 'UNKNOWN';
			$total_procs++;
			$server_count[$name]++;
			if(isset($summary[$state])) $summary[$state]++;
			else $summary['OTHER']++;

			if($state=='FATAL' || $state=='BACKOFF'){
				$health = 'danger';
			}elseif(($state=='STOPPED' || $state=='EXITED' || $state=='UNKNOWN') && $health!='danger'){
				$health = 'warning';
			}elseif($state=='STARTING' && $health=='success'){
				$health = 'info';
			}
		}
		$server_health[$name] = $health;
	}

	// Distribui os servidores em colunas equilibrando pela quantidade de processos,
	// para evitar buracos quando um servidor tem muito mais processos que outro.
	$cols = ($this->config->item('supervisor_cols')==2) ? 2 : 3;
	$column_servers = array_fill(0, $cols, array());
	$column_weight  = array_fill(0, $cols, 0);
	foreach($list as $name=>$procs){
		$target = 0;
		for($i=1; $i<$cols; $i++){
			if($column_weight[$i] < $column_weight[$target]) $target = $i;
		}
		$column_servers[$target][] = $name;
		$column_weight[$target] += $server_count[$name] + 2;
	}
	?>

	<div class="container-fluid">

		<div class="row stats-row">
			<div class="col-xs-6 col-sm-4 col-md-2">
				<div class="stat-box">
					<span class="stat-value"><?php echo count($list);?></span>
					<span class="stat-label">Servers</span>
				</div>
			</div>
			<div class="col-xs-6 col-sm-4 col-md-2">
				<div class="stat-box">
					<span class="stat-value"><?php echo $total_procs;?></span>
					<span class="stat-label">Processes</span>
				</div>
			</div>
			<div class="col-xs-6 col-sm-4 col-md-2">
				<div class="stat-box stat-success">
					<span class="stat-value"><?php echo $summary['RUNNING'];?></span>
					<span class="stat-label">Running</span>
				</div>
			</div>
			<div class="col-xs-6 col-sm-4 col-md-2">
				<div class="stat-box stat-default">
					<span class="stat-value"><?php echo $summary['STOPPED'];?></span>
					<span class="stat-label">Stopped</span>
				</div>
			</div>
			<div class="col-xs-6 col-sm-4 col-md-2">
				<div class="stat-box stat-warning">
					<span class="stat-value"><?php echo $summary['EXITED'] + $summary['OTHER'];?></span>
					<span class="stat-label">Exited</span>
				</div>
			</div>
			<div class="col-xs-6 col-sm-4 col-md-2">
				<div class="stat-box stat-danger">
					<span class="stat-value"><?php echo $summary['FATAL'];?></span>
					<span class="stat-label">Fatal</span>
				</div>
			</div>
		</div>

		<?php
		if($muted){
			echo '<div class="alert alert-warning">';
			echo '<span class="glyphicon glyphicon-volume-off"></span> Sound muted for '.timespan(time(),$muted);
			echo '<a href="?mute=-1" class="pull-right"><span class="glyphicon glyphicon-volume-up"></span> Unmute</a>';
			echo '</div>';
		}
		?>

		<div class="row server-grid">
			<?php
			$alert = false;
			foreach($column_servers as $names){
			?>
			<div class="col-md-<?php echo (12/$cols);?>">
				<?php
				foreach($names as $name){
					$procs = $list[$name];
					$parsed_url = parse_url($cfg[$name]['url']);
					if ( isset($cfg[$name]['username']) && isset($cfg[$name]['password']) ){
						$base_url = 'http://' . $cfg[$name]['username'] . ':' . $cfg[$name]['password'] . '@';
					}else{
						$base_url = 'http://';
					}
					$ui_url = $base_url . $parsed_url['host'] . ':' . $cfg[$name]['port']. '/';
				?>
				<div class="panel panel-<?php echo $server_health[$name];?> server-panel">
					<div class="panel-heading">
						<?php
						if(!isset($procs['error'])){
						?>
						<div class="pull-right server-btns">
							<a href="<?php echo site_url('/control/stopall/'.$name); ?>" class="btn btn-xs btn-default" title="Stop all processes"><span class="glyphicon glyphicon-stop"></span> Stop all</a>
							<a href="<?php echo site_url('/control/startall/'.$name); ?>" class="btn btn-xs btn-success" title="Start all processes"><span class="glyphicon glyphicon-play"></span> Start all</a>
							<a href="<?php echo site_url('/control/restartall/'.$name); ?>" class="btn btn-xs btn-primary" title="Restart all processes"><span class="glyphicon glyphicon-refresh"></span> Restart all</a>
						</div>
						<?php
						}
						?>
						<h3 class="panel-title">
							<span class="glyphicon glyphicon-hdd"></span>
							<a href="<?php echo $ui_url; ?>"><?php echo $name; ?></a>
							<span class="badge"><?php echo $server_count[$name];?></span>
							<?php if($this->config->item('show_host')){ ?><small class="server-host"><?php echo $parsed_url['host']; ?></small><?php } ?>
							<?php if(isset($cfg[$name]['username'])){ ?><span class="glyphicon glyphicon-lock" title="Authenticated server connection"></span><?php } ?>
							<?php if(!is_array($version[$name])){ ?><small class="server-version">supervisord <?php echo $version[$name]; ?></small><?php } ?>
						</h3>
					</div>
					<div class="table-responsive">
					<table class="table table-condensed table-hover server-table">
						<thead>
							<tr>
								<th>Process</th>
								<th>Status</th>
								<th class="text-right">Uptime</th>
								<th class="actions-col"></th>
							</tr>
						</thead>
						<tbody>
						<?php
						$CI = &get_instance();
						foreach($procs as $item){

							if(!is_array($item)){
									// Not having array means that we have error.
									echo '<tr class="danger"><td colspan="4"><span class="glyphicon glyphicon-exclamation-sign"></span> '.$item.'</td></tr>';
									echo '<tr><td colspan="4" class="hint">For troubleshooting <a href="https://github.com/lucenarenato/supervisord-monitor#troubleshooting" target="_blank">check this guide</a></td></tr>';
									continue;
							}

							if($item['group'] != $item['name']) $item_name = $item['group'].":".$item['name'];
							else $item_name = $item['name'];

							$check = $CI->_request($name,'readProcessStderrLog',array($item_name,-1000,0));
							if(is_array($check)){
								if(isset($check['error'])){
									// Sem arquivo de stderr (NO_FILE) ou falha de leitura:
									// nao e um alerta, apenas ausencia de log.
									$check = false;
								}elseif(isset($check['log'])){
									$check = $check['log'];
								}else{
									$check = print_r($check,1);
								}
							}

							$pid = $uptime = '&nbsp;';
							$status = $item['statename'];
							if($status=='RUNNING'){
								$class = 'success';
								list($pid,$uptime) = explode(",",$item['description']);
							}
							elseif($status=='STARTING') $class = 'info';
							elseif($status=='FATAL') { $class = 'danger'; $alert = true; }
							elseif($status=='STOPPED') $class = 'default';
							else $class = 'warning';

							$uptime = str_replace("uptime ","",$uptime);
							?>
							<tr>
								<td class="process-name"><?php
									echo $item_name;
									if($check){
										$alert = true;
										echo '<span class="pull-right"><a href="'.site_url('/control/clear/'.$name.'/'.$item_name).'" id="'.$name.'_'.$item_name.
												'" onclick="return false" data-toggle="popover" data-message="'.htmlspecialchars($check).'" data-original-title="'.
												$item_name.'@'.$name.'" class="pop btn btn-xs btn-warning" aria-label="View stderr log"><span class="glyphicon glyphicon-warning-sign"></span></a></span>';
									}
									?>
								</td>
								<td class="status-col"><span class="label label-<?php echo $class;?>"><?php echo $status;?></span></td>
								<td class="text-right uptime-col" title="<?php echo htmlspecialchars(isset($item['description'])?$item['description']:'');?>"><?php echo $uptime;?></td>
								<td class="actions-col">
									<div class="actions">
										<?php if($status=='RUNNING'){ ?>
										<a href="<?php echo site_url('/control/stop/'.$name.'/'.$item_name);?>" class="btn btn-xs btn-default" title="Stop"><span class="glyphicon glyphicon-stop"></span></a>
										<a href="<?php echo site_url('/control/restart/'.$name.'/'.$item_name);?>" class="btn btn-xs btn-default" title="Restart"><span class="glyphicon glyphicon-refresh"></span></a>
										<?php } if($status=='STOPPED' || $status == 'EXITED' || $status=='FATAL'){ ?>
										<a href="<?php echo site_url('/control/start/'.$name.'/'.$item_name);?>" class="btn btn-xs btn-success" title="Start"><span class="glyphicon glyphicon-play"></span></a>
										<?php } ?>
									</div>
								</td>
							</tr>
							<?php
						}

						?>
						</tbody>
					</table>
					</div>
				</div>
				<?php
				}
				?>
			</div>
			<?php
			}
			?>
		</div>

		<?php
		if($alert && !$muted && $this->config->item('enable_alarm')){
			echo '<embed height="0" width="0" src="'.base_url('/sounds/alert.mp3').'">';
		}
		if($alert){
			echo '<script>document.title = "!!! WARNING !!!";</script>';
		}
		?>
	</div>

	<footer class="footer">
		<div class="container-fluid">
			<p class="text-muted">Powered by <a href="https://github.com/lucenarenato/supervisord-monitor" target="_blank">Supervisord Monitor</a> &middot; Page rendered in <strong>{elapsed_time}</strong> seconds</p>
		</div>
	</footer>

	<script>
	function show_content($param){
		stopTimer();
		$time = new Date();
		$message = $(this).data('message')+"\n"+$time+"\n"+$time.toDateString();
		$title = $(this).data('original-title');
		$content = '<div class="popover-log">'+nl2br($message)+'</div>';
		$content+= '<div class="popover-actions clearfix">';
		$content+= '<div class="pull-left"><form method="get" action="<?php echo $this->config->item('redmine_url');?>" style="display:inline" target="_blank">';
		$content+= '<input type="hidden" name="issue[subject]" value="'+$title+'"/>';
		$content+= '<input type="hidden" name="issue[description]" value="'+$message+'"/>';
		$content+= '<input type="hidden" name="issue[assigned_to_id]" value="<?php echo $this->config->item('redmine_assigne_id');?>"/>';
		$content+= '<input type="submit" class="btn btn-xs btn-default" value="Start New Ticket"/>';
		$content+= '</form></div>';
		$content+= '<div class="pull-right"><a href="#" onclick="$(\'#'+$(this).attr('id')+'\').popover(\'hide\');startTimer();" class="btn btn-xs btn-primary">OK</a> ';
		$content+= '<a href="'+$(this).attr('href')+'" class="btn btn-xs btn-danger">Clear</a></div>';
		$content+= '</div>';
		return $content;
	}
	$('.pop').popover(
		{content: show_content, html: true, placement: 'bottom', container: 'body', trigger: 'click'}
	);
	var $refresh = <?php echo $this->config->item('refresh');?>;
	var $timer = false;
	$(window).on('load', function() {
		if($refresh > 0) {
			startTimer();
		}
	});
	function stopTimer(){
		$('#refresh').text('p');
		clearInterval($timer);
	}
	function startTimer(){
		if($timer)  stopTimer();
		$timer = setInterval(timer,999);
	}
	function timer(){
		$refresh--;
		$('#refresh').text($refresh);
		if($refresh<=0){
			stopTimer();
			location.href="<?php echo site_url() ?>";
		}
	}
	function nl2br (str, is_xhtml) {
		var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br ' + '/>' : '<br>';
		return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
	}
	</script>

</body>
</html>
