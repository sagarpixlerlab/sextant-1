## Laravel Sextant.
Laravel Sextant - this is powerful, smart and simple filtration engine for api calls for laravel.

Capability Tale:

| Laravel            | Filter  |
| ------------------ | ------- |
| >=5.4.x < 5.8.x   | [2.0.x](https://git.attractgroup.com/amondar/LaravelFilterAndSorting)
| >=5.8              | ^1.0.7

## Installation
- add below code into `composer.json`:
 
```json
"repositories": [
    {
        "url": "https://git.attractgroup.com/amondar/sextant.git",
        "type": "git"
    }
]
"require": {
  "amondar/sextant": "^1.0.7"
}
```

- Publish configuration:

```bash
php artisan vendor:publish --provider="Amondar\Sextant\SextantServiceProvider"
```



## Basics
Use `HasSextantOperations` trait in the model. Redeclare `extraFields()` function if you need to make 
some relations publicly accessible for the api calls. Use below example:  

```php
    class SomeModel extends Model
    {
        use HasSextantOperations;

        ...

        public function extraFields()
        {
            return ['someRelation'];
        }

        ...

        public function someRelation()
        {
            return $this->hasOne(SomeRelation::class);
        }
    }
```

By default, after using sextant trait, it connects to the model as scope operation. So you can call it as below:

```php
  public function index(Request $request)
  {
      return SomeModel::withSextant($request)->get()
  }
```

## Filter Usage
You must use `$_GET` parameter called `filter` to interacts with model filtering. Filter parameter expects json string with parameters.

### Available operations:

**isNull** - not required parameter parameter, `accept` true or `false`, add `AND $key IS NULL` or `AND $key IS NOT NULL` to sql query string

**operation** - is a logical operation identifier, accept `'>','<','>=','<=','<>','not in','in','like'`.

**value** - sample value, can be array, if `"operation":"in"` or `"operation":"not in"` used.

**from** и **to** - range filtration operations. Can work stand alone. If fates received, they processed as dates by `Carbon::parse()` function and converted to mysql format. 
 For filtration we use `>=` и `<=`, so values from and to included into range too. In situations, where operations `<=` and `>=` not allowed - you can use your own filtration. 
 Example - `{"from": { "value":  "123","operation":">"}}`. 

NOTE: For simple equals query, use simple notation - `{"id":1}`, then query will have a look as `WHERE id = 1`.

#### Query examples:

```php
/message?filter={"created_at":{"from":"2016-02-20","to":"2016-02-24 23:59:59"}, "id":{"operation":"not in", "value":[2,3,4]}}

/message?filter={"id":{"from":2,"to":5}}

/message?filter={"id":{"to":5}} и /message?filter={"id":{"operation":"<=","value":5}} - эквивалентны

/message?filter={"updated_at":{"isNull":true}}

/message?filter={"answer":{"operation":"like","value":"Partial search string"}} - will be converted into: WHERE answer LIKE "%Partial search string%"

/message?filter={"answer":"Full search string"} - точный поиск по строке
```

### Filtrate models by relations existence:

```php
/users?filter={"posts":{"operation":"has", "value":3, "condition":">"}} - find users with minimum 3 posts. Condition can be - <,>,=,<> and combinations.
/users?filter={"posts":{"operation":"doesnthave"}} - find users without posts.
```

### Filtrate by relations:

```php
/message?filter={"user.name":"asd"}
```
You myst define allowed relations into `extraFields` function. Use relations filters same as with simple fields in the root model.

### Filtrate relations
You can filtrate relations by using `$_GET` parameter `filterExpand`. It works same as simple filter, but filtrate relation data.
```php
    public function index(Request $request)
    {
      return SomeModel::withSextant($request, [], ['except' => ['expand' => ['someExpandName']]])->get();
    }
    
    public function index(Request $request)
    {
        return SomeModel::withSextant($request, [], ['only' => ['expand' => ['someAnotherExpandName']]])->get();
    }
```
After that, if you send in request any filtration operations or sorting, or expand - given relation will be skipped.
**NOTE**: 'only' restriction also works with empty array -  `['only' => ['expand' => []]]` - will disallow any expands and filtration operations on them on given request.

## Sorting
You can sort data by any model or relations fields. To use sorting just define `$_GET` parameter called `sort`.
Sextant sort by default as `asc`, if you need `desc` sorting - you need to add minus sign in sort definition prefix. Example - `-views_count`

#### Sorting examples:
```php
/message?sort=id

/message?sort=-id

/message?sort=user.name
```
You can define multiple sorting, just separate sort string with commas. Example -`?sort=user.first_name,user.last_name` 

### Relations sorting
You can sort relation. Use `$_GET` parameter `sortExpand`. It works same as simple sorting on root model, but sorts relation data.


### Relation expands
You can request relation expand. You can request allowed by `extraFields()` relations only. Add $_GET parameter called expand to the request.
**NOTE:** Not allowed relations will be ignored. Parameter `expand` required for `filterExpand` and `sortExpand` operations.  

#### Expand examples:
```php
  /message?expand=user
```

###response example:
```json
  {
    "id": 1,
    "message": "some message",
    "user_id": 1,
    "user": {
        "id": 1,
        "name": "Some username"
    }
  }
```

### Restrictions
Since 1.1.4 version you can provide restrictions for filter operations. For example, we have a goal to disable some expands on index method of our CRUD to achieve full protection of speed, 
then we can use restrictions:
 
```php
['only' => ['expand' => ['{EXPAND_NAME}', ...]]];
```

### Searching
For short `sql like` search was added `search` parameter. This parameter works with root model and any relations fields.

#### Searching example:
```php
  /message?search={"query":"some","fields":"relation1.field|table_field|relation2.field"}
```

### Laravel scopes usage.
In Sextant you can user native laravel scope functionality. Working with scopes is simple as filtering:

```php
  /message?filter={"answer":{"operation":"scope","value":"someScopeName"}} - without params
  /message?filter={"answer":{"operation":"scope","value":"someScopeName","parameters":["some", "params for ",  "scope input"]}} - with params
  /message?filter={"answer":{"operation":"scope","value":"someScopeName","parameters":"one param"}} - with one param
  /message?filter={"owner.first_name":{"operation":"scope","value":"someRelationScopeName","parameters":"Some name"}} - on relation usage.
```
`NOTE:` If working with relations - scope must be present in relation model.
You can simply connect scope to the request. Just user it like below:

```php
  /posts?scopes='[{"name":"someScopeName","parameters":[]}]'
```
You must pass `parameters` key, when using `scopes` directive, even if `parameters` are empty.
To protect models scopes from not allowed requests use `extraScopes()` function. For example: 

```php
    class SomeModel extends Model
    {
        use HasSextantOperations;

        ...

        public function extraScopes()
        {
            return ['someScopeName'];
        }

        ...

        public function scopeSomeScopeName($query)
        {
            $query->...
        }
    }
```

**!!!NOTE**: Using scopes for open world brings a lot of protection issues. You can create `orWhere` queries or etc,
 which will break your protection of search. To avoid that:
 
```php
 class SomeModel extends Model
 {
     public function scopeSomeScopeName($query)
     {
         $query->where('some_field', 'search')
            ->where(function($query){
                $query->where('field', 'some search)
                    ->orWhere('field', 'some other search');
            })
     }
 }
```

This code will convert into SQL:
```sql
  SELECT * FROM `messages` WHERE `some_field` = 'search' AND (`field` = 'some search' OR `field` = 'some other search') 
```

## Raw usage
You can use Sextant without model calls or on models that does not use Sextant operations trait. For example:
```php
 app('sextant')->filtrate(User::class, $request, $predefinedParameters);
 
 \\or
 
 Sextant::filtrate(User::class, $request, $predefinedParameters);
```

As you can see, you can use filtration on model class. If model does not have Sextant operations, we will user our default model,
which we will convert into yours:
```php
 $newModel = new SextantModel();
 $newModel->setTable($model->getTable());
 $newModel->setKeyName($model->getKeyName());
 $newModel->setKeyType($model->getKeyType());

 return $newModel->withSextant($request, $params);
```
**NOTE:** that default models has empty `extraFields` and `extraScopes`.
**NOTE:** that you can pre-define parameters for filtration by passing third parameter into `->withSextant(...)` function. Just pass parameters as array. Example:

```php
 app('sextant')->filtrate(User::class, $request, ['sort' => '-created_at', 'filter' => ['posts.views_count' => ['operation' => '>=', 'value' => 10]]]);
 
 \\or
 
 Sextant::filtrate(User::class, $request, ['sort' => '-created_at', 'filter' => ['posts.views_count' => ['operation' => '>=', 'value' => 10]]]);
```

## Extensions
You can create your own extensions for Sextant engine. Just add your new action into config.
`NOTE:` Every new action must implement  `Amondar\Sextant\Contracts\SextantActionContract::class`


