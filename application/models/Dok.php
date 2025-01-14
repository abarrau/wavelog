<?php

class DOK extends CI_Model {

	function __construct() {
		$this->load->library('Genfunctions');
	}

	function get_dok_array($bands, $postdata, $location_list) {
		$doks = array();

		$list = $this->getDoksFromDB($location_list);
		foreach ($this->getSdoksFromDB($location_list) as $sdok) {
			$list[] = $sdok;
		}
		foreach ($list as $dok) {
			$doks[$dok->COL_DARC_DOK]['count'] = 0;
		}

		$qsl = $this->genfunctions->gen_qsl_from_postdata($postdata);

		foreach ($bands as $band) {
			foreach ($list as $dok) {
				$bandDok[$dok->COL_DARC_DOK][$band] = '-';
			}

			if ($postdata['worked'] != NULL) {
				$dokBand = $this->getDokWorked($location_list, $band, $postdata);
				foreach ($dokBand as $line) {
					if (array_key_exists($line->COL_DARC_DOK, $bandDok)) {   /* For now ignore DOKs which are logged but not existing in the official lists any more */
						$bandDok[$line->COL_DARC_DOK][$band] = '<div class="bg-danger awardsBgDanger"><a href=\'javascript:displayContacts("' . $line->COL_DARC_DOK . '","' . $band . '","All","All","' . $postdata['mode'] . '","DOK", "")\'>W</a></div>';
						$doks[$line->COL_DARC_DOK]['count']++;
					}
				}
			}

			if ($postdata['confirmed'] != NULL) {
				$dokBand = $this->getDokConfirmed($location_list, $band, $postdata);
				foreach ($dokBand as $line) {
					if (array_key_exists($line->COL_DARC_DOK, $bandDok)) {   /* For now ignore DOKs which are logged but not existing in the official lists any more */
						$bandDok[$line->COL_DARC_DOK][$band] = '<div class="bg-success awardsBgSuccess"><a href=\'javascript:displayContacts("' . $line->COL_DARC_DOK . '","' . $band . '","All","All","' . $postdata['mode'] . '","DOK", "'.$qsl.'")\'>C</a></div>';
						$doks[$line->COL_DARC_DOK]['count']++;
					}
				}
			}

			// We want to remove the worked DOKs in the list, since we do not want to display them
			if ($postdata['worked'] == NULL) {
				$dokBand = $this->getDokWorked($location_list, $postdata['band'], $postdata);
				foreach ($dokBand as $line) {
					unset($bandDok[$line->COL_DARC_DOK]);
				}
			}

			// We want to remove the worked DOKs in the list, since we do not want to display them
			if ($postdata['confirmed'] == NULL) {
				$dokBand = $this->getDokConfirmed($location_list, $postdata['band'], $postdata);
				foreach ($dokBand as $line) {
					unset($bandDok[$line->COL_DARC_DOK]);
				}
			}
		}

		foreach ($list as $dok) {
			if($doks[$dok->COL_DARC_DOK]['count'] == 0) {
				unset($bandDok[$dok->COL_DARC_DOK]);
			}
		}

		// We want to hide NM as marking not having a DOK at all
		if (isset($bandDok['NM'])) {
			unset($bandDok['NM']);
		}

		if (isset($bandDok)) {
			return $bandDok;
		} else {
			return 0;
		}

	}

	function getDokWorked($location_list, $band, $postdata) {
		$bindings=[];
		$sql = "SELECT DISTINCT COL_DARC_DOK FROM " . $this->config->item('table_name') . " thcv
			WHERE station_id IN (" . $location_list . ") AND COL_DARC_DOK <> '' AND COL_DARC_DOK <> 'NM'";

		if ($postdata['mode'] != 'All') {
			$sql .= " AND (COL_MODE = ? OR COL_SUBMODE = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}
		$sql .= $this->addDokTypeToQuery($postdata['doks']);
		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);
		$sql .= " AND NOT EXISTS (SELECT 1 from " . $this->config->item('table_name') .
			" WHERE station_id in (" . $location_list .
			") AND COL_DARC_DOK = thcv.COL_DARC_DOK AND COL_DARC_DOK <> '' AND COL_DARC_DOK <> 'NM' ";
		$sql .= $this->addDokTypeToQuery($postdata['doks']);
		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$sql .= ")";
		$query = $this->db->query($sql,$bindings);

		return $query->result();

	}

	function getDokConfirmed($location_list, $band, $postdata) {
		$bindings=[];
		$sql = "SELECT DISTINCT COL_DARC_DOK FROM " . $this->config->item('table_name') . " thcv
			WHERE station_id IN (" . $location_list . ") AND COL_DARC_DOK <> '' AND COL_DARC_DOK <> '' AND COL_DARC_DOK <> 'NM'";
		if ($postdata['mode'] != 'All') {
			$sql .= " AND (COL_MODE = ? OR COL_SUBMODE = ?)";
			$bindings[]=$postdata['mode'];
			$bindings[]=$postdata['mode'];
		}
		$sql .= $this->addDokTypeToQuery($postdata['doks']);
		$sql .= $this->genfunctions->addBandToQuery($band,$bindings);
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}


	function addDokTypeToQuery($doks) {
		$sql = '';
		if ($doks == 'dok') {
			$sql .= " AND COL_DARC_DOK REGEXP '^[A-Z][0-9]{2}$'";
		} else if ($doks == 'sdok') {
			$sql .= " AND COL_DARC_DOK NOT REGEXP '^[A-Z][0-9]{2}$'";
		}
		return $sql;
	}

	function get_dok_summary($bands, $postdata, $location_list) {
		foreach ($bands as $band) {
			$worked = $this->getSummaryByBand($band, $postdata, $location_list);
			$confirmed = $this->getSummaryByBandConfirmed($band, $postdata, $location_list);
			$dokSummary['worked'][$band] = $worked[0]->count;
			$dokSummary['confirmed'][$band] = $confirmed[0]->count;
		}


		$workedTotal = $this->getSummaryByBand($postdata['band'], $postdata, $location_list);
		$confirmedTotal = $this->getSummaryByBandConfirmed($postdata['band'], $postdata, $location_list);

		$dokSummary['worked']['Total'] = $workedTotal[0]->count;
		$dokSummary['confirmed']['Total'] = $confirmedTotal[0]->count;

		return $dokSummary;
	}

	function getSummaryByBand($band, $postdata, $location_list) {
		$bindings=[];
		$sql = "SELECT count(distinct thcv.COL_DARC_DOK) AS count FROM " . $this->config->item('table_name') . " thcv";
		$sql .= " WHERE station_id IN (" . $location_list . ') AND COL_DARC_DOK != "" AND COL_DARC_DOK <> "NM"';
		if ($band == 'SAT') {
			$sql .= " AND thcv.COL_PROP_MODE = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			$this->load->model('bands');
			$bandslots = $this->bands->get_worked_bands('dok');
			$bandslots_list = "'".implode("','",$bandslots)."'";
			$sql .= " AND thcv.COL_BAND in (" . $bandslots_list . ")";
		} else {
			$sql .= " AND thcv.COL_PROP_MODE !='SAT'";
			$sql .= " AND thcv.COL_BAND = ?";
			$bindings[]=$band;
		}
		if ($postdata['doks'] == 'dok') {
			$sql .= " AND COL_DARC_DOK REGEXP '^[A-Z][0-9]{2}$'";
		} else if ($postdata['doks'] == 'sdok') {
			$sql .= " AND COL_DARC_DOK NOT REGEXP '^[A-Z][0-9]{2}$'";
		}
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}

	function getSummaryByBandConfirmed($band, $postdata, $location_list){
		$bindings=[];
		$sql = "SELECT count(distinct thcv.COL_DARC_DOK) AS count FROM " . $this->config->item('table_name') . " thcv";
		$sql .= " WHERE station_id IN (" . $location_list . ') AND COL_DARC_DOK != "" AND COL_DARC_DOK <> "NM"';
		if ($band == 'SAT') {
			$sql .= " AND thcv.COL_PROP_MODE = ?";
			$bindings[]=$band;
		} else if ($band == 'All') {
			$this->load->model('bands');
			$bandslots = $this->bands->get_worked_bands('dok');
			$bandslots_list = "'".implode("','",$bandslots)."'";
			$sql .= " AND thcv.COL_BAND in (" . $bandslots_list . ")";
		} else {
			$sql .= " AND thcv.COL_PROP_MODE !='SAT'";
			$sql .= " AND thcv.COL_BAND = ?";
			$bindings[]=$band;
		}
		if ($postdata['doks'] == 'dok') {
			$sql .= " AND COL_DARC_DOK REGEXP '^[A-Z][0-9]{2}$'";
		} else if ($postdata['doks'] == 'sdok') {
			$sql .= " AND COL_DARC_DOK NOT REGEXP '^[A-Z][0-9]{2}$'";
		}
		$sql .= $this->genfunctions->addQslToQuery($postdata);
		$query = $this->db->query($sql,$bindings);
		return $query->result();
	}

	function getDoksFromDB($location_list) {
		$sql = 'SELECT DISTINCT `COL_DARC_DOK` FROM '.$this->config->item('table_name');
		$sql .= " WHERE station_id IN (" . $location_list . ') AND COL_DARC_DOK != "" AND COL_DARC_DOK <> "NM"';
		$sql .= " AND COL_DARC_DOK REGEXP '^[A-Z][0-9]{2}$'";
		$sql .= " ORDER BY COL_DARC_DOK ASC";
		$query = $this->db->query($sql);
		return $query->result();
	}

	function getSdoksFromDB($location_list) {
		$sql = 'SELECT DISTINCT `COL_DARC_DOK` FROM '.$this->config->item('table_name');
		$sql .= " WHERE station_id IN (" . $location_list . ') AND COL_DARC_DOK != "" AND COL_DARC_DOK <> "NM"';
		$sql .= " AND COL_DARC_DOK NOT REGEXP '^[A-Z][0-9]{2}$'";
		$sql .= " ORDER BY COL_DARC_DOK ASC";
		$query = $this->db->query($sql);
		return $query->result();
	}
}
?>
