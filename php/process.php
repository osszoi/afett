<?php
	ini_set('memory_limit', '-1');
	ini_set('max_execution_time', 10800);

	include_once('utils.php');
	include_once('databasehandler.php');

	$debug = isset($_GET['debug']);

	$dbh = new DatabaseHandler();

	if (!isset($_GET['a'])) {
		echo "Debe especificar qué se va a cargar.";
		die();
	}

	if (isset($_GET['fixIndicadores'])) {
		$dbh->arreglar_indicadores();

		redirect();
		die();
	}

	if (isset($_GET['soloAct'])) {
		$fn = "actualizar_".$_GET['a'];
		$dbh->$fn();

		if (!isset($_GET['noRedirect'])) {
			if ($_GET['a'] == 'indicadores') {
				echo "Redirigiendo en 3 segundos para generar los resultados consolidados..<br>";
				echo "
					<script> setTimeout(() => { window.location = '".getUrl()."&fixIndicadores' }, 3000)  </script>
				";
			}
			else {
				redirect();
			}
		}
		die();
	}

	if (isset($_GET['fixResultados'])) {
		$dbh->arreglar_resultados();

		echo "Redirigiendo en 3 segundos para generar los resultados consolidados..<br>";
		echo "
			<script> setTimeout(() => { window.location = '".getUrl()."&soloAct' }, 3000)  </script>
		";
		die();
	}

	$fc = readFileContent("../upload/{$_GET['a']}");
	$arr = convertToArray($fc); 

	$dbFields = [];

	if ($_GET['a'] == 'personas') {
		$dbFields = array(
			"cedula" => ["campo" => "cedula", "tabla" => "AR_Persona", "pk" => true, "picklist" => false],
			"empresa" => ["campo" => "nombre", "tabla" => "AR_Empresa", "pk" => false, "picklist" => true, "createFn" => "crear_empresa"],
			"sede" => ["pk" => false, "picklist" => true, "createFn" => "crear_sede", "checkFn" => "check_sede"],
			"unidad" => ["pk" => false, "picklist" => true, "createFn" => "crear_unidad", "checkFn" => "check_unidad"],
			"proceso" => ["pk" => false, "picklist" => true, "createFn" => "crear_proceso", "checkFn" => "check_proceso"],
			"rol_integral" => ["pk" => false, "picklist" => true, "createFn" => "crear_rol_integral", "checkFn" => "check_rol_integral"],
			"puesto_organizativo" => ["pk" => false, "picklist" => true, "createFn" => "crear_puesto_organizativo", "checkFn" => "check_puesto_organizativo"],
		);
	}
	elseif ($_GET['a'] == 'resultados') {
		$dbFields = array(
			"id_resultado" => ["pk" => true, "tabla" => "AR_Resultado", "campo" => "id_resultado", "picklist" => true, "createFn" => "crear_resultado", "checkFn" => "check_resultado"],
		);
	}
	elseif ($_GET['a'] == 'indicadores') {
		$dbFields = array(
			"indicador" => ["pk" => false, "picklist" => true, "checkFn" => "check_indicador"],
			"id_resultado" => ["pk" => true, "tabla" => "AR_Resultado_Indicador", "campo" => "id_resultado", "picklist" => true, "createFn" => "crear_indicadores", "checkFn" => "check_indicadores"],
		);
	}
	elseif ($_GET['a'] == 'objetivos') {
		// Para hacerlo valido
	}
	else {
		echo "Archivo no válido.";
		die();
	}

	$pks = [];
	$i = 0;

	foreach ($arr as $r) {
		$i++;

		if (isset($_GET['max'])) {
			if ($i > intval($_GET['max']) * (isset($_GET['p']) ? intval($_GET['p']) + 1 : 1)) {
				echo $debug ? "Proceso terminado. Registro maximo alcanzado $i de " . (intval($_GET['max']) * (isset($_GET['p']) ? intval($_GET['p']) : 1)) . "<br>" : "";

				if (!isset($_GET['noAutoJump'])) {
					echo "Redirigiendo en 3 segundos para seguir procesando..<br>";
					echo "
						<script> setTimeout(() => { window.location = '".getNextUrl()."' }, 3000)  </script>
					";
				}

				die();
			}
		}

		if (isset($_GET['p']) && isset($_GET['max'])) {
			if ($i <= (isset($_GET['p']) ? intval($_GET['p']) : 1) * intval($_GET['max'])) {
				echo $debug ? "Saltando registro $i hasta llegar a " . ((isset($_GET['p']) ? intval($_GET['p']) : 1) * intval($_GET['max'])) . "<br>" : "";
				continue;
			}

			if ($i > (isset($_GET['p']) ? intval($_GET['p']) + 1 : 1) * intval($_GET['max'])) {
				echo $debug ? "Proceso terminado. Se alcanzó el registro $i de " . ($i * intval($_GET['p'])) . "<br>" : "";
				break;
			}
		}

		if (count($r) == 0) {
			continue;
		}

		// Solo objetivos
		if ($_GET['a'] == 'objetivos') {
			if (!$dbh->check_objetivo($r)) {
				$dbh->crear_objetivo($r);
			}
			else {
				echo "Objetivo ya existente: ";
				print_r($r);
				echo "<br><br>";
			}
			continue;
		}

		// Solo poara resultados
		if ($_GET['a'] == 'resultados') {
			if (!$dbh->check_resultado($r)) {
				$dbh->crear_resultado($r);
			}

			continue;
		}

		$pk = "";
		$pkField = "";
		$pkExists = null;
		// get the pk
		foreach ($r as $k => $v) {
			if (array_key_exists($k, $dbFields)) {
				if ($dbFields[$k]["pk"]) {
					$pk = $v;
					$pkField = $k;
					$pkExists = $dbh->check_existencia($dbFields[$k]["campo"], $v, $dbFields[$k]["tabla"]);
				}
			}
		}

		if ($pk == "") {
			echo "Clave primaria no encontrada.<br><br>Información de error:<br>";
			echo "Array length: " . count($r) . "<br>Array: ";
			print_r($r);
			echo "<br>";
			continue;
		}

		if (!in_array($pk, $pks)) {
			//$pks[] = $pk;

			foreach ($r as $k => $v) {
				if (array_key_exists($k, $dbFields)) {
					if ($dbFields[$k]["picklist"]) {
						$c_ = isset($dbFields[$k]["checkFn"]) ? $dbh->$dbFields[$k]["checkFn"]($r) : $dbh->check_existencia($dbFields[$k]["campo"], $v, $dbFields[$k]["tabla"]);

						if ($c_) {
							echo $debug ? "'$k' con valor '$v' si existe.<br>" : "";
						}
						else {
							echo $debug ? "'$k' con valor '$v' debe ser creado.<br>" : "";

							if (isset($dbFields[$k]["createFn"])) {
								$dbh->$dbFields[$k]["createFn"]($r);
								echo $debug ? "'$k' con valor '$v' creado con éxito.<br>" : "";
							}
						}
					}
				}
			}

			// check if needs to be created or updated
			if ($pkExists) {
				echo $debug ? "<b>'$pkField' con valor '$pk' debe ser actualizado</b><br>" : "";
				
				if ($_GET['a'] == 'personas') {
					$dbh->actualizar_persona($r);
				}

				echo "<b>'$pkField' con valor '$pk' actualizado con éxito</b><br>";
			}
			else {
				echo $debug ? "<b>'$pkField' con valor '$pk' debe ser creado</b><br>" : "";
				
				if ($_GET['a'] == 'personas') {
					$dbh->crear_persona($r);
				}

				echo "<b>'$pkField' con valor '$pk' creado con éxito</b><br>";
			}
		}

		echo $debug ? "<br>" : "";
	}

	if ($_GET['a'] == "personas" && !isset($_GET['actCoaches'])) {
		echo "Redirigiendo en 3 segundos para procesar los coaches..<br>";
		echo "
			<script> setTimeout(() => { window.location = '".getUrl()."&actCoaches' }, 3000)  </script>
		";

		die();
	}

	if (($_GET['a'] == "resultados" || $_GET['a'] == "indicadores") && !isset($_GET['soloAct'])) {
		$nextOption = "soloAct";

		if ($_GET['a'] == "resultados") {
			$nextOption = "fixResultados";
		}

		echo "Redirigiendo en 3 segundos para generar los resultados consolidados..<br>";
		echo "
			<script> setTimeout(() => { window.location = '".getUrl()."&".$nextOption."' }, 3000)  </script>
		";

		die();
	}

	if (!isset($_GET['noRedirect']))
		redirect();
?>