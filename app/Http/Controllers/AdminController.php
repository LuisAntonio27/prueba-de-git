<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterPartidaRequest;
use App\Http\Requests\UserRequest;
use App\Models\Catalogos\CatAreas;
use App\Models\Catalogos\CatPartidas;
use App\Models\Menu;
use App\Models\ModelHasRole;
use App\User;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Yajra\Datatables\Datatables;

class AdminController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        $this->middleware('auth');
    }

    public function index(){
        $datos = User::where('id', auth()->user()->id)->get();
        return view('usuarios.perfil', compact('datos'));
    }

    public function dashboard()
    {
        $perfil = Auth::user()->hasAnyRole(['SuperAdmin', 'Admin']);
        if($perfil == true){
             return view('admin.dashboard');
         }else{
            return view('/home');
        }
     }

    public function create() {
        //Evitamos mostrar roles de superadmin al usuario NO Superadmin
        $user = auth()->user();
        $rol = $user->getRoleNames();
        $areas = CatAreas::all();
        if($rol[0] == 'SuperAdmin'){
            $roles = DB::table('roles')->get();
        }
        elseif($rol[0] == 'AdminUsuarios'){
            $roles = DB::table('roles')->whereIn('name', ['Area'])->get();
        }
        else{
            $roles = DB::table('roles')->whereNotIn('name', ['SuperAdmin'])->get();
        }

        return view('modals/users/add_user')->with('roles', $roles)->with('areas', $areas);
    }

    public function create_rol()
    {
        return view('modals.roles.add_new_role');
    }

    public function create_partida(){
        return view('modals/partidas/add_partida');
    }

    public function store_partida(RegisterPartidaRequest $request){
        $nombre_servicio = $request->nombre_servicio;
        $concepto = $request->concepto;
        $partida = $request->partida;
        $tipo_partida = $request->tipo_partida;
        $padre = ($tipo_partida === 'bien') ? '2' : '1';
        \Log::info(__METHOD__.' Crear nueva partida');
        try {
            DB::beginTransaction();
            $registerPartida = new CatPartidas();
            $registerPartida->nombre_servicio = $nombre_servicio;
            $registerPartida->concepto = $concepto;
            $registerPartida->partida = $partida;
            $registerPartida->tipo_partida = $tipo_partida;
            $registerPartida->estatus = true;
            $registerPartida->save();

            $orden = Menu::getOrden();
            $registerMenu = new Menu();
            $registerMenu->menu = $nombre_servicio . " (" . $partida . ")";
            $registerMenu->slug = $partida;
            $registerMenu->padre = $padre;
            $registerMenu->orden = $orden;
            $registerMenu->activo = true;
            $registerMenu->ruta = "/".strtolower($nombre_servicio);
            $registerMenu->ajax = true;
            $registerMenu->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'data' => 'Se cargo correctamente el menu',
                'code' => 'REGPART001'
            ], 200);
        } catch (\Exception $e) {
            \Log::warning(__METHOD__."--->Line:".$e->getLine()."----->".$e->getMessage());
            DB::rollback();
            return response()->json([
                'success' => false,
                'data' => 'Error al guardar',
                'code' => 'REGPART002'
            ], 200);
        }
    }

    public function store_new_role(Request $request) {
        \Log::info(__METHOD__.' Crear nuevo Rol');
        $existeRol = Role::where('name','=',$request->rol)->get();
        if(!empty($existeRol)){
            try {
                    $rol = $request->rol;
                    DB::beginTransaction();
                    $new_rol = Role::create([
                            'name' => $rol,
                            'guard_name' => 'web'
                        ]);
                    $response = array('success' => true, 'message' => 'Rol creado correctamente.');
                    DB::commit();
                } catch (\Exception $th) {
                \Log::warning(__METHOD__."--->Line:".$th->getLine()."----->".$th->getMessage());
                DB::rollback();
                    $response = ['success' => false, 'message' => 'Error al guardar el usuario.'];
                }
            }else{
                $response = ['success' => false, 'message' => 'Ya existe ese rol.'];
            }
        return $response;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\usuarios\Users  $users
     * @return \Illuminate\Http\Response
    */
    public function edit(Request $request){
        //Evitamos mostrar roles de superadmin al usuario NO Superadmin
        $user = auth()->user();
        $rol = $user->getRoleNames();
        $areas = CatAreas::all();
        if($rol[0] == 'SuperAdmin'){
            $roles = DB::table('roles')->get();
        }else{
            $roles = DB::table('roles')->whereNotIn('name', ['SuperAdmin'])->get();
        }
        $id = $request->id;
        $datosRoles = User::getRol($id);
        $user = User::find($id);
        return view('modals/users/edit_user')
            ->with(compact('user'))
            ->with(compact('datosRoles'))
            ->with(compact('roles'))
            ->with(compact('areas'));
    }

    public function editar_roles_permisos(Request $request){
       $id=$request->id;
       $role = Role::findOrFail($id);
        $permissions = Permission::all();

        return view('admin.roles.editar_roles_permisos', compact('role', 'permissions'));
    }

    public function store(UserRequest $request){
        \Log::info(__METHOD__.' Crear nuevo Usuario');
         try {
            $id_rol = $request->id_rol;
            DB::beginTransaction();
           // dd($request->id_area);
            $user = User::create([
                    'name' => $request->nombreU,
                    'id_area' => $request->id_area, //Se requiere cambiar por área
                    'apellido_paterno' => $request->apellido_paterno,
                    'apellido_materno' => $request->apellido_materno,
                    'id_rol' => $id_rol,
                    //'password' => bcrypt($request->rfc),
                    'password' => bcrypt("CDMX123"),
                    'email' => $request->email,
                    'estatus' => 1, //Siempre activo
                    'rfc' => strtoupper($request->rfc),
                    'curp' => strtoupper($request->rfc)
                ]);
            $grol = DB::table('roles')->where('id', '=', $id_rol)->first();
            $user->assignRole($grol->name);
            /*if($grol){
                $user->assignRole($grol->name);
                $user->givePermissionTo('Ver');

            }*/
            // Le asignamos el rol
            if($grol->id == 5){
                $user->givePermissionTo('Area');
            }else{
            $user->givePermissionTo('Ver');
            }
            DB::commit();
            return response()->json(['status'=>'valido', 'data' => 'El usuario se creo correctamente', 'code' => 'SER001'],200);
        } catch (\Exception $th) {
            \Log::warning(__METHOD__."--->Line:".$th->getLine()."----->".$th->getMessage());
            DB::rollback();
            return response()->json(['status'=>'no_valido', 'data' => 'Error al guardar el usuario.', 'code' => 'SER002'],200);
        }
    }

    /**
     * Actualizar usuario.
     *
     * @param  Request  $request
     * @param  Users  $users
     * @return Response
     */
    public function update(UserRequest $request, User $users){
        \Log::info(__METHOD__);
        try {
            DB::beginTransaction();
            $id = $request->id_usuario;
            $id_rol = $request->id_rol;
            $estatus = ($request->estatus_user == "on" ) ? 1 : 0;
            //Obtenemos el usuario
            $users = User::findOrFail($id);
            //Asignamos los valoes a actualizar
            $users->name = $request->nombreU;
            $users->id_area = $request->id_area;
            $users->apellido_paterno = $request->apellido_paterno;
            $users->apellido_materno = $request->apellido_materno;
            $users->email = $request->email;
            $users->estatus = $estatus;
            $users->id_rol = $id_rol;
            if($request->rfc){
                $users->rfc = $request->rfc;
                $users->curp = $request->rfc;
            }
            $users->save();
            $idUsuarioRol = DB::table('model_has_roles')->where('model_id', '=', $id)->first();
            $idUsuarioRolAnterior = $idUsuarioRol->role_id;

            ModelHasRole::where('model_id', $id)
               ->where('role_id', $idUsuarioRolAnterior)
               ->update(['role_id' => $id_rol]);

            DB::commit();
            return response()->json(['status'=>'valido', 'data' => 'Usuario actualizado satisfactoriamente.', 'code' => 'SER003'],200);
        } catch (\Exception $th) {
            DB::rollback();
            \Log::warning(__METHOD__."--->Line:".$th->getLine()."----->".$th->getMessage());
            return response()->json(['status'=>'no_valido', 'data' => 'Error al guardar el usuario.', 'code' => 'SER004'],200);
        }
    }

    public function listar_usuarios(){
        return view('usuarios.listar_usuarios');
    }

    public function data_listar_usuarios(){
        //Evitamos mostrar usuarios de superadmin al usuario NO Superadmin
        $user = auth()->user();
        $rol = $user->getRoleNames();
        if($rol[0] == 'SuperAdmin'){
            $users = User::all();
        }else{
            $users = User::whereNotIn('id_rol', [1])->get();
        }
        return Datatables::of($users)->toJson();
    }

    public function listar_roles(){
        $roles = Role::all();//Get all roles
        //return view('roles.index')->with('roles', $roles);
    return view('admin.roles.listar_roles')->with('roles', $roles);
    }

    public function data_listar_roles(){
        $role = Role::all();//Get all roles
        //  $permisos = =Permissions::getAllPermisos()
        //$role->permissions()->pluck('name');
        return Datatables::of($role)->toJson();
    }

    public function listar_permisos(){
        $permisos = Permission::all();
        return view('admin.permisos.listar_permisos', compact('permisos'));
    }

    public function create_permiso(){
        //$datos = Role::all();
        return view('modals.permisos.add_new_permiso');
    }

    public function store_new_permiso(Request $request) {
        \Log::info(__METHOD__.' Crear nuevo permiso');
        $existePermiso = Permission::where('name','=',$request->rol)->get();
        if(!empty($existePermiso)){
            try {
                  $rol = $request->rol;
                   DB::beginTransaction();
                    $new_rol = Permission::create([
                            'name' => $rol,
                            'guard_name' => 'web'
                        ]);
                    $response = array('success' => true, 'message' => 'Permiso creado correctamente.');
                    DB::commit();
                } catch (\Exception $th) {
                \Log::warning(__METHOD__."--->Line:".$th->getLine()."----->".$th->getMessage());
                DB::rollback();
                    $response = ['success' => false, 'message' => 'Error al guardar el permiso.'];
                }
        }else{
            $response = ['success' => false, 'message' => 'Ya existe ese permiso.'];
        }
        return $response;
    }

    public function data_listar_permisos(){
        return Datatables::of(Permissions::getAllPermisos())
            ->toJson();
        //return view('usuarios.listar_usuarios', compact('users'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateRoles(Request $request, $id){

        $role = Role::findOrFail($id);//Get role with the given id
        //Validate name and permission fields
        $this->validate($request, [
            'name'=>'required|max:10|unique:roles,name,'.$id,
            'permissions' =>'required',
        ]);

        $input = $request->except(['permissions']);
        $permissions = $request['permissions'];
        $role->fill($input)->save();

        $p_all = Permission::all();//Get all permissions

        foreach ($p_all as $p) {
            $role->revokePermissionTo($p); //Remove all permissions associated with role
        }

        foreach ($permissions as $permission) {
            $p = Permission::where('id', '=', $permission)->firstOrFail(); //Get corresponding form //permission in db
            $role->givePermissionTo($p);  //Assign permission to role
        }

        return redirect()->route('roles.index')
            ->with('flash_message',
             'Role'. $role->name.' updated!');
    }

}
