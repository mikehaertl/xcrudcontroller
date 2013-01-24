<?php
/**
 * XCrudController
 *
 * A base class to quickly build customized CRUD interfaces.
 *
 * @version 1.0.0
 * @author Michael Härtl <haertl.mike@gmail.com>
 */
abstract class XCrudController extends Controller
{
    /**
     * @var string render the list+filter by default
     */
    public $defaultAction = 'list';

    /**
     * @var string name of model (required)
     */
    public $modelName;

    /**
     * @var mixed name of filter model. If empty, $modelName will be used.
     */
    public $filterModelName;

    /**
     * @var string name of the form view for create/update
     */
    public $formView = 'form';

    /**
     * @var string name of the list view that renders the filter and the _items partial
     */
    public $listView = 'list';

    /**
     * @var string name of the partial view that renders the items (CListView/CGridView)
     */
    public $listPartial = '_items';

    /**
     * @var string name of the detail view
     */
    public $detailView = 'detail';

    /**
     * @var string name of scenario to use for create operation
     */
    public $createScenario = 'create';

    /**
     * @var string name of scenario to use for update operation
     */
    public $updateScenario = 'update';

    /**
     * @var string name of scenario to use for filter operation
     */
    public $filterScenario = 'filter';

    /**
     * @var array list of available actions. Useful to disable some action.
     */
    public $crudActions = array('edit', 'list', 'view', 'delete');

    /**
     * @var string name of URL parameter with return URL for list
     */
    public $returnVar = 'returnUrl';

    private $_model = false;
    private $_filterModel = false;

    /**
     * Handle these requests:
     *
     *  GET without ajax    : render 'list' with items and filter form
     *  GET with ajax       : render only '_items' (to update CGridView/CListView)
     *
     * Both requests can contain filter data in $_GET
     */
    public function actionList()
    {
        if(!in_array('list',$this->crudActions))
            throw new CHttpException('404 Not Found');

        $this->render(Yii::app()->request->isAjaxRequest ? $this->listPartial : $this->listView);
    }

    /**
     * Handle these requests:
     *
     *  GET without 'id'    : render create item form
     *  GET with 'id'       : render update item form
     *  POST with 'ajax'    : validate CActiveForm
     *  POST without 'ajax' : save item (create/update) and go to returnUrl
     */
    public function actionEdit()
    {
        if(!in_array('edit',$this->crudActions))
            throw new CHttpException('404 Not Found');

        if (isset($_POST['ajax']))
            $this->validateAjax($this->getModel(),strtolower($this->modelName).'-form');

        if (isset($_POST[$this->modelName]))
        {
            $model = $this->getModel();
            $model->attributes = $_POST[$this->modelName];
            if ($model->save())
            {
                $key = $this->modelName.($model->isNewRecord ? '-created' : '-updated');
                Yii::app()->user->setFlash($key,true);
                $this->redirect($this->returnUrl);
            }
        }
        $this->render($this->formView);
    }

    /**
     * Handle these requests:
     *
     *  GET with 'id'       : render detail view
     */
    public function actionView()
    {
        $this->render($this->detailView);
    }

    /**
     * Handle these requests:
     *
     *  GET/POST with 'id' : delete item and go to returnUrl
     */
    public function actionDelete()
    {
        if(!in_array('delete',$this->crudActions))
            throw new CHttpException('404 Not Found');

        $this->getModel()->delete();

        $this->redirect($this->returnUrl);
    }

    /**
     * Helper method to create the URL to edit/view an item
     *
     * This can be used in CListView/CGridView to render the edit button or view links.
     *
     * @param mixed $data the current record from a data provider
     * @param string $action the item action. Only 'edit' or 'view' make sense here.
     * @param mixed $returnUrl wether to add return URL to list. false to disable, string for custom URL
     * @return string the URL to the edit page of that item
     */
    public function createItemUrl($data, $action, $returnUrl = true)
    {
        // Avoid repetitive function calls to fetch same data on consecutive calls
        static $url;
        static $pk;
        if($url===null) {
            $url = $this->url;
            $pk = $data->tableSchema->primaryKey;

            if(is_array($pk))
                throw new CException('XCrudController does not support composite keys.');
        }

        $params = array($pk => $data->primaryKey);

        if($returnUrl)
            $params[$this->returnVar] = $returnUrl===true ? $url : $returnUrl;

        return $this->createUrl($action, $params);
    }

    /**
     * Get the URL where we should return to, after an action was successful
     *
     * This is the return URL supplied in $_GET[$this->returnVar]. If no such parameter is
     * available, then this will be the list page.
     *
     * The special values 'edit' or 'view' can be used as URL param to indicate, that we want to
     * return to the edit/view page after a new record was created.
     *
     * @return string the URL to return to
     */
    public function getReturnUrl()
    {
        if (isset($_GET[$this->returnVar]))
        {
            if($_GET[$this->returnVar]==='edit' || $_GET[$this->returnVar]==='view')
                if($this->model->isNewRecord)
                    return $this->createUrl('list');
                else
                    return $this->createUrl($_GET[$this->returnVar],array('id' => $this->model->id));
            else
                return $_GET[$this->returnVar];
        }
            else
                return $this->createUrl('list');
    }

    /**
     * Return the filter model with filter settings applied
     *
     * @return CModel the filter model with attributes assigned and $filterScenario applied
     */
    public function getFilterModel()
    {
        if ($this->_filterModel===false)
        {
            $modelName = $this->filterModelName===null ? $this->modelName : $this->filterModelName;
            $this->_filterModel = new $modelName;
            if ($this->filterScenario!==null)
                $this->_filterModel->scenario = $this->filterScenario;
            $this->assignFilterModelAttributes($this->_filterModel);
        }
        return $this->_filterModel;
    }

    /**
     * Get the model for one of two possible scenarios:
     *
     *  create : new model, no id is provided in $_GET
     *  update : existing model, id is available in $_GET
     *
     * @param bool $required wether the id is required in $_GET
     * @return CActiveRecord the apropriate AR object for the current scenario
     */
    public function getModel($required=true)
    {
        if ($this->_model===false)
        {
            if(isset($_GET['id']))
            {
                $this->_model = call_user_func(array($this->modelName,'model'))->findByPk($_GET['id']);
                if ($required && $this->_model===null)
                    throw new CHttpException(404,'Object not found');
                $this->_model->scenario = $this->updateScenario;
            }
            else
            {
                $this->_model = new $this->modelName;
                $this->_model->scenario = $this->createScenario;
            }
        }
        return $this->_model;
    }

    /**
     * @return string URL to current page
     */
    public function getUrl()
    {
        return Yii::app()->request->url;
    }

    /**
     * Assign filter attributes to the filter model.
     *
     * By default this checks for $_GET[<classname>] and assigns the values given there.
     * You may want to override this to use a custom URL pattern for your filter attributes
     * like ...?dateStart=2012-10-01&dateEnd=2012-11-01.
     *
     * @param CModel $model the filter model that the attributes should get assigned to
     */
    protected function assignFilterModelAttributes($model)
    {
        $class = get_class($model);
        if (isset($_GET[$class]))
            $model->attributes = $_GET[$class];
    }

    /**
     * Perform validation on AJAX requests from a CActiveForm
     *
     * @param CModel the model class to validate
     * @param string ID of CActiveForm form
     */
    protected function validateAjax($model,$id)
    {
        if (isset($_POST['ajax']) && $_POST['ajax']===$id)
        {
            echo CActiveForm::validate($model);
            Yii::app()->end();
        }
    }
}
