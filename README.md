XCrudController
===============

A base class to quickly build customized CRUD interfaces.

# Features

 *  **Create, list, view,  edit and delete actions.**
    Follow DRY and don't repeat the same CRUD logic in all your controllers.
 *  **Powerful filter pattern.**
    Use a dedicated filter form in your listing to build convenient search interfaces.
 *  **Helpers for URL creation.**
    Take the user back to the same listing page after editing/viewing a record.

# Quickstart

## 1 A simple controller

To get started you only have to supply the name of the ActiveRecord class
that you want to build a CRUD for:

```php
<?php
Yii::import('ext.xcrudcontroller.XCrudController');
class UserController extends XCrudController
{
    public $modelName = 'User';
}
```

## 2 Create view files

You have to supply the `list`, `edit` and `detail` views.

> **Note**: The markup is very reduced. In a real life project you'd of course
> style these elements apropriately. You could also start from the CRUD templates
> as generated from Gii. But you have to rearrange and modify them a little.

### 2.1 List view

The `list` view contains the filter form and renders the partial view for
the actual items via `_items`.

*protected/views/user/list.php:*

```php
<?php $model = $this->filterModel; ?>

<div>
    <h2>Search users</h2>
    <?php echo CHtml::beginForm(array('list'),'get'); ?>
        <label>
            Username
            <?php echo CHtml::activeTextField($model, 'username'); ?>
        </label>
        <label>
            Email
            <?php echo CHtml::activeTextField($model, 'email'); ?>
        </label>
        <?php echo CHtml::submitButton('Search'); ?>
    <?php echo CHtml::endForm(); ?>
</div>

<?php $this->renderPartial('_items'); ?>
```

> **Note:** In real life your filter form is usually offering way more search options.
> You would better implement this through a dedicated filter form. See below for details.

*protected/views/user/_items.php:*

```php
<?php $this->widget('zii.widgets.grid.CGridView',array(
    'dataProvider' => $this->filterModel->search(),
    'columns' => array(
        'id',
        array(
            'name'  => 'Username',
            'value' => 'CHtml::link($data->username, Yii::app()->controller->createItemUrl($data, "view"))',
            'type'  => 'raw',
        ),
        'email',
        array(
            'class'=>'CButtonColumn',
            'template'=>'{update} {delete}',
            'buttons'=>array(
                'update'=>array(
                    'url'=>'Yii::app()->controller->createItemUrl($data,"edit")'
                ),
            ),
        ),
    )
)); ?>
```

Notice how the links to view and edit an item are built through `createItemUrl()`. This
adds the current list page URL as URL parameter so that it's very easy to link back
to this search result page.

### 2.2 Create/edit form

*protected/views/user/form.php:*

```php
<?php $model = $this->model; ?>

<h1><?php echo $model->isNewRecord ? 'Add new user' : 'Edit user' ?></h1>

<?php $form=$this->beginWidget('CActiveForm',array(
    'id' => 'user-form',
));?>
    <div>
        <?php echo $form->label($model, 'username'); ?>
        <?php echo $form->textField($model, 'username'); ?>
        <?php echo $form->error($model, 'username'); ?>
    </div>
    <div>
        <?php echo $form->label($model, 'email'); ?>
        <?php echo $form->textField($model, 'email'); ?>
        <?php echo $form->error($model, 'email'); ?>
    </div>
    <div>
        <?php echo $form->label($model, 'password'); ?>
        <?php echo $form->textField($model, 'password'); ?>
        <?php echo $form->error($model, 'password'); ?>
    </div>

    <?php echo CHtml::submitButton($model->isNewRecord ? 'Create' : 'Save') ?>
    <?php echo CHtml::link('Cancel', $this->returnUrl); ?>

<?php $this->endWidget(); ?>
```

> **Note:** The form name must be `strtolower($this->modelName).'-form'` if
> you want to use AJAX validation with `CActiveForm`.


### 2.3 Detail view

*protected/views/user/form.php:*

```php
<?php $model = $this->model; ?>

<div>
    <?php $this->widget('zii.widgets.CDetailView', array(
        'data' => $model,
    )); ?>
</div>

<?php echo CHtml::link('Back', $this->returnUrl); ?>
```

# Configuration

## Views

As you've seen above, 4 view files must be provided for the controller:

 *  `list`: Contains the filter form and renders the `_items` partial
 *  `_items`: Renders the items (e.g. with `CListView`/`CGridView`)
 *  `form`: Renders the create/edit form
 *  `detail`: Renders the detail view

The view names can be configured in `$listView`, `$listPartial`, `$formView`
and `$detailView` properties.

The views use a pull mechanism to fetch their data:

 *  `$this->model`: Returns the current model for `form` and `detail` view
 *  `$this->filterModel`: Returns the filter model for `list` and `_items` view.
    The data provider for the items is available through the `search()` method.
 *  `$this->returnUrl`: Contains the URL which takes the user back to the search
    result page. This is useful on `edit` and `view` pages.

After a record was created or updated a flash message with the key `$modelName-created` or
`$modelName-updated` is set respectively. You can use this to show a success message
in the view which you show after saving a record.

> **Note:** You can control, which page you want to show after you created a new record.
> By default this will be the list page (with filters reset). You can also show the
> detail view of the new record or stay on the edit page, if you want. Therefore you
> have to create the "Add" link like this:
>
>   `CHtml::link('Add user', array('edit', $this->returnVar=>'view'));`
>
> Instead of 'view' you can also use 'edit' to redirect to the edit form of the
> newly created record.

## Scenarios

You can configure several scenarios which are set on the `$model` or `$filterModel` for
specific actions:

 * `$createScenario` : Set when creating a new record. Default is `create`.
 * `$updateScenario` : Set when updating a record. Default is `update`.
 * `$filterScenario` : Set on the filter model when assigning search parameters. Default is `filter`.

## Other options

If you don't want to provide all actions, you can disable some of them through
`$crudActions`. By default this is `array('edit', 'list', 'view', 'delete').

The name of the URL parameter for the return URL is set through `$returnVar` and defaults
to `returnUrl`.

# Advanced tricks

## Using a filter model

In the simplest case a filter model is an ActiveRecord as created by Gii. But if you want
to keep your code cleaner, i'd recommend to use a separate model file for all your filter
concerns. This has the advantage that you don't clutter up your ActiveRecords with search
related code anymore and can focus on the search logic here:


```php
<?php
class UserFilter extends CFormModel
{
    public $username;
    public $email;

    // You can add many more attributes here, to offer convenient search options
    public $ageMin;
    public $ageMax;

    // All these attributes must be declared as 'safe'
    public function rules()
    {
        return array(
            array('username,email', 'safe'),
        );
    }

    // As usual, build the search criteria from the current attribute values.
    // In real life you may even use a dedicated search engine like Solr -
    // as long as it returns a valid data provider everything will still work.
    // All attribute values are translated into query conditions.
    public function search()
    {
        $criteria = new CDbCriteria;

        if(!empty($this->username))
            $criteria->compare('username', $this->name);

        if(!empty($this->email))
            $criteria->compare('email', $this->city);

        if(!empty($this->ageMin))
            $criteria->addCondition('birthday < SUBDATE(NOW(), INTERVAL '.(int)$this->ageMin.' YEAR)');

        if(!empty($this->ageMax))
            $criteria->addCondition('birthday > SUBDATE(NOW(), INTERVAL '.(int)$this->ageMax.' YEAR)');

        return new CActiveDataProvider('User', array(
            'criteria' => $criteria,

            // Add more options as required, e.g. for sorting
        ));
    }
}
```

In the controller you have to configure this model in `$filterModelName`:

```php
<?php
Yii::import('ext.xcrudcontroller.XCrudController');
class UserController extends XCrudController
{
    public $modelName = 'User';
    public $filterMlodelName = 'UserFilter';
}
```

## How to get nicer URLs for your search results

The URL parameters to a search result page look like this:

    ...&User[username]=test&User[email]=@example.com

This does not look very nice. It would be much better to have easier to read
search parameters, just like Google or other search engines do it:


    ...&username=test&email=@example.com

It's very easy to achieve this. You have to override the `assignFilterModelAttributes()`
method in your controller like this:

```php
<?php
    protected function assignFilterModelAttributes($model)
    {
        $model->attributes = $_GET;
    }
```

It is safe to do this in this case: We only have the search form parameters
in $_GET, plus maybe the pagination and sorting options. But as long as they
don't interfere with your search model attributes everything is still fine.

But you also have to change the filter form a little because by default Yii
will create form element names like `User[username]` whereas we want them
to be only `username` now:

```php
    <?php echo CHtml::beginForm(array('list'),'get'); ?>
        <label>
            Username
            <?php echo CHtml::textField('username', $model->username); ?>
        </label>
        ...
```
