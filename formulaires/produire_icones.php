<?php


function formulaires_produire_icones_charger_dist() {

	// retrouver tous les chemins ayant des themes
	$dirs = find_dirs_in_path('prive/themes/spip/images');
	sort($dirs);
	// on crée une liste tel que :
	// cle => valeur
	// plugins/toto => ../plugins/toto/prive/themes/spip/images
	$liste = array();
	foreach ($dirs as $d) {
		// on enleve ../ et prive/themes/spip/images
		$nom = explode('/', $d);
		$nom = array_slice($nom, 1, -4);
		$nom = implode('/', $nom);
		$liste['./'.$nom] = $d;
	}

	$images = array();
	$repertoire_destination = '';

	// on recupere toutes les images du repertoire demandé.
	// et on verifie que le répertoire est écrivable
	if ($source = _request('repertoire_racine')) {
		if ($img = preg_files($source, '.*(png|gif)$')) {
			// on ne prend pas en compte les del/add/edit/new deja existantes
			$variantes = preg_files($source, '.*-(del|add|edit|new)-.*(png|gif)$');
			$img = array_diff($img, $variantes);
			foreach ($img as $i) {
				$nom = basename($i);
				$images[$nom] = $i;
			}
		}

		if (is_writable($source)) {
			$repertoire_destination = $source;
		} else {
			$erreur = '
				Ce répertoire source n\'est pas accessible en écriture.
				Les images calculées seront placées dans tmp/cache/icones
				et non dans le répertoire source.
			';
			$repertoire_destination = sous_repertoire(_DIR_CACHE, 'icones');
		}
	}
	
	$valeurs = array(
		'images' => '',
		'repertoire_racine' => '',
		'repertoire_racine_old' => '',
		'repertoire_destination' => $repertoire_destination,
		'liste_repertoires' => $liste,
		'liste_images' => $images,
	);

	if ($erreur) {
		$valeurs['message_info'] = $erreur;
	}
	return $valeurs;
}


function formulaires_produire_icones_verifier_dist() {
	$erreurs = array();
	$source = _request('repertoire_racine');
	$images = _request('images');
	if (!$source OR (_request('repertoire_racine_old')==$source AND (!$images OR !count($images)))) {
		$erreurs['message_erreur'] = 'Votre saisie contient des oublis !';
	}
	return $erreurs;
}


/**
 * Trouver des repertoires particuliers dans le path 
 *
 * @param string $chercher
 * 		'prive/themes/spip' par exemple pour chercher tous les chemins
 * disposant de cette suite de repertoires
 * 
 * @return array Liste des chemins trouves
**/
function find_dirs_in_path($chercher) {
	$liste_dossiers = array();

	if (!$chercher) return array();
	
	// on retrouve le chemin souhaite prive themes spip
	$chercher = explode('/', $chercher);
	$dir_base = array_shift($chercher);

	// Parcourir le chemin
	foreach (creer_chemin() as $d) {
		$dd = $d . $dir_base;
		if (@is_dir($dd)) {
			if (!count($chercher)) {
				$liste_dossiers[] = $dd;
			} else {
				// on descend dans les sous chemins
				$chercher_copie = $chercher;
				while ($dir = array_shift($chercher_copie)) {
					$dd .= '/' . $dir;
					if (@is_dir($dd)) {
						if (!count($chercher_copie)) {
							$liste_dossiers[] = $dd;
						}
					}
				}
			}
		}
	}

	return $liste_dossiers;
}

/**
 * Extrait des informations sur le nom d'une image
 *
 * Taille
 * 'organisations-32.png' => 32
 * 'organisations.png => ''
 * 'organisations-del-32.png => 32
 *
 * Nom
 * 'organisations-32.png' => organisations
 * 
 * Extension
 * 'organisations-32.png' => png
 *
 * @param string $nom
 * 		Nom de l'image
 * 
 * @param string $type
 * 		Type de valeur souhaitee (taille, nom ou extension)
 * 		En son absence, retourne le tableau de toutes les infos
 * 
 * @return mixed
 * 		Information demandee, sinon toutes les informations calculées.
**/
function themes_extraire_image($nom_image, $type='') {
	$infos = array();
	
	$n = pathinfo($nom_image);
	$infos['extension'] = $n['extension'];
	
	if (isset($n['filename'])) {
		$n = $n['filename'];
	} else {
		$n = substr($nom_image, 0, -strlen($n['extension']) - 1);
	}

	// on a n = 'organisations-32'
	$infos['taille'] = '';
	$infos['nom'] = $n;
	$ni = explode('-', $n);
	// au moins un -
	if (count($ni) > 1) {
		$t = array_pop($ni);
		// le dernier est un chiffre (et uniquement un chiffre).
		if ((string)intval($t) === $t) {
			$infos['taille'] = $t;
			// le nom est soustrait de sa taille.
			$infos['nom'] = implode('-', $ni);
		}
	}

	if ($type) {
		return $infos[$type];
	}
	
	return $infos;
}


/*

ANCIEN CODE POUR MEMOIRE



[(#REM)
	Copier les icones d'un chemin vers un autre en les modifiant
]
#SET{dest,#CHEMIN{extensions/themes/prive/themes/cowsepia/images}}
<BOUCLE_taille(DATA){source tableau,#LISTE{48,32,24,16}}{si 0}>
	<BOUCLE_icones(DATA){source glob,#CHEMIN{extensions/themes/prive/themes/cow/images}|concat{'/*-',#VALEUR,'.png'}}>
	[(#VALEUR|balise_img|image_sepia)]
	[(#VALEUR|balise_img|image_sepia|extraire_attribut{src}|copy{#GET{dest}|concat{/#VALEUR|basename}})]
	</BOUCLE_icones>
	<hr />
</BOUCLE_taille>

[(#REM)

	Combiner les icones avec les add/del/new/
]
#SET{source,#CHEMIN{themes/spip/images}}
<BOUCLE_tailles(DATA){source tableau,#LISTE{32,24,16}}{si 1}>
	<BOUCLE_fonction(DATA){source tableau,#LISTE{add,del,new,edit}}>
		[(#SET{f,[(#CHEMIN{[prive/themes/spip/images/(#_fonction:VALEUR)-[(#_tailles:VALEUR)].png]})]})]
		<BOUCLE_iconesc(DATA){source glob,#GET{source}|concat{'/auteur-5poubelle?',#_tailles:VALEUR,'.png'}}>
		[(#SET{nom,[(#VALEUR|basename{[-(#_tailles:VALEUR).png]}|concat{'-',#_fonction:VALEUR,'-',#_tailles:VALEUR,'.png'})]})]
		[(#VALEUR|balise_img|image_masque{#GET{f},mode=normal})]
		[(#VALEUR|balise_img|image_masque{#GET{f},mode=normal}|extraire_attribut{src}|copy{#GET{source}|concat{'/',#GET{nom}}})]
		</BOUCLE_iconesc>
	</BOUCLE_fonction>
	<hr />
</BOUCLE_tailles>

[(#REM)

	Mixer les icones sepia avec les icones couleur
]
#SET{coul,#CHEMIN{extensions/themes/fatcow/32x32}}
#SET{dest,#CHEMIN{extensions/themes/fatcow-retro/32x32}}
<BOUCLE_iconesm(DATA){source glob,#GET{coul}|concat{'/*.png'}}{si 0}>
#SET{img,#VALEUR|image_sepia
			|image_masque{#VALEUR|image_alpha{90},mode=normal}}
[(#GET{img})]
#SET{n,#GET{dest}|concat{/#VALEUR|basename}}
[(#GET{n}|file_exists|non)
[(#GET{img}
			|extraire_attribut{src}|copy{#GET{n}}|vide
)]
</BOUCLE_iconesm>

*/

?>
