<?php

class MediasController extends AppController {

    var $components = array('Session');
    var $uses = array('Vcloud', 'Media');

    public function beforeFilter() {
        parent::beforeFilter();
	$this->Auth->allow("index_api");
    }

    function isAuthorized($user) {
	if ($user['is_admin']) {
            return true;
        }

        if (in_array($this->action, array('index','index_api','does_media_exist_api','upload_media_api'))) {
            return true;
        }

	if (in_array($this->action, array('delete'))) {
	    $org_id = $this->Vcloud->get_orgid_by_mediaid($this->passedArgs['media_id']);
            if (isset($user['permissions'][$org_id]['write_permission']) && $user['permissions'][$org_id]['write_permission']) {
                return true;
            }
        }

	// Default to not allowing them access
        return false;
    }
    function index_api() {
	$catalog_name=$this->passedArgs['catalog_name'];
	$medias = $this->Vcloud->list_media_in_catalog($catalog_name);
	$this->set('medias', $medias);
        $this->set('_serialize', array('medias'));
    }
    function index(){
        $this->set('page_for_layout', 'catalogs');
	$catalog_name=$this->passedArgs['catalog_name'];
	$this->set("title_for_layout", $catalog_name . " Catalog");
	if (!isset($catalog_name))
	{
		$this->Session->setFlash('This is an invalid URL, you must pass in a catalog name', 'flash_bad');
		$this->redirect($this->referer());
	}
	$medias = $this->Vcloud->list_media_in_catalog($catalog_name);
	$this->set('medias', $medias);
	$this->set("catalog_name",$this->passedArgs['catalog_name']);
    }
    function delete(){
        try{
            $this->Vcloud->delete_media($this->passedArgs['media_id']);
            $this->Session->setFlash('This media has now being deleted', 'flash_good');
        } catch (Exception $e) {
            $this->Session->setFlash('Unable to delete this media - ' . $e, 'flash_bad');
        }
        $this->redirect($this->referer());
    }
    function does_media_exist_api()
    {
	$catalog_name=$this->passedArgs['catalog_name'];
	$item_name=$this->passedArgs['item_name'];
	if ($this->Vcloud->does_media_exist($catalog_name, $item_name))
        {
		$result="true";
        }
	else {
		$result="false";
	}
	$this->set('result', $result);
	$this->set('_serialize', array('result'));
    }
    function upload_media_api()
    {
	$catalog_name = $this->passedArgs['catalog_name'];
	$uniquedir = $this->passedArgs['uniquedir'];
	$full_dir_path = 'files/isos/'.$uniquedir.DS;
                try {
                        foreach(new DirectoryIterator('files/isos/'.$uniquedir) as $file)
                        {
                                if ($file->isFile()) {
                                        $file_path=$file->getPath(). '/' . $file->getFileName();
					break;
                                }
                        }
                        $this->Vcloud->upload_local_iso($catalog_name,$file_path,$file->getFileName());
                } catch (Exception $e) {
                        $this->deleteDirectory($full_dir_path);
                        throw new InternalErrorException("Something went wrong adding the uploaded iso to the catalog, contact the cloud team with this message if its not " . $e);
                }
	$this->deleteDirectory($full_dir_path);
	$result="true";
	$this->set('result', $result);
        $this->set('_serialize', array('result'));
    }

    function deleteDirectory($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
                if ($item == '.' || $item == '..') continue;
                if (!$this->deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) return false;
        }
        return rmdir($dir);
    }
}

?>
