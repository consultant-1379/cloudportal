<?php
class FileUploadsController extends AppController {

    var $name = 'FileUploads';
    var $uses = array('Vcloud', 'FileUpload');

    public function beforeFilter() {
        parent::beforeFilter();
    }

    function isAuthorized($user) {
	return true;
    }
    function upload()
    {
        $this->layout = "ajax";
        App::import('Vendor','UploadHandler',array('file' => 'UploadHandler/UploadHandler.php'));

	$uniquedir = $this->passedArgs['uniquedir'];

        $options = array
        (
            'script_url' => FULL_BASE_URL.DS.'files/isos/'.$uniquedir.DS,
            'upload_dir' => APP.WEBROOT_DIR.DS.'files'.DS.'isos'.DS.$uniquedir.DS,
            'upload_url' => FULL_BASE_URL.DS.'files/isos/'.$uniquedir.DS,
            'max_number_of_files' => 3,
	    //'mkdir_mode' => 0755,
            'thumbnail' => array
            (
                'max_width' => 150,
                'max_height' => 150
            )
        );

        $upload_handler = new UploadHandler($options, $initialize = false);
        switch ($_SERVER['REQUEST_METHOD'])
        {
            case 'HEAD':
            case 'GET':
                $upload_handler->get();
                break;
            case 'POST':
		$catalog_name = $this->passedArgs['catalog_name'];

                // Upload it
                $upload_handler->post();
                break;
            case 'DELETE':
                $upload_handler->delete();
                break;
            default:
                header('HTTP/1.0 405 Method Not Allowed');
        }
        exit;
    }

   }
?>
