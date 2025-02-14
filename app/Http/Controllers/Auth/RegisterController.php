<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\MenuSettings;
use App\welcomeemail;
use App\PaymentType;
use App\AppSettings;
use App\ProfileInfo;
use App\Tree_Table;
use App\Settings;
use App\Packages;
use App\Activity;
use App\Country;
use App\Voucher;
use App\Emails;
use App\User;
use App\PaypalDetails;
use App\PendingTransactions;
use App\ProfileModel;
use App\PurchaseHistory;
use App\RsHistory;
use App\Sponsortree;
use App\Ranksetting;
use App\IpnResponse;
use App\Mail_template;

use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use CountryState;
use Storage;
use GeoIP;
use Crypt;
use Mail;
use Input;
use Session;
use Redirect;
use Auth;
use Response;
//paypal

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\ExecutePayment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Transaction;

use PayPal\Api\ChargeModel;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Plan;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Common\PayPalModel;

// use to process billing agreements
use PayPal\Api\Agreement;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Api\ShippingAddress;

use Srmklive\PayPal\Services\ExpressCheckout;


class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
  
    protected static  $provider;
    private $apiContext;
    private $mode;
    private $client_id;
    private $secret;



    public function __construct()
    {
       
       self::$provider = new ExpressCheckout;  
        if(config('paypal.settings.mode') == 'live'){
            $this->client_id = config('paypal.live_client_id');
            $this->secret = config('paypal.live_secret');
        } else {
            $this->client_id = config('paypal.sandbox_client_id');
            $this->secret = config('paypal.sandbox_secret');
        }
        
        // Set the Paypal API Context/Credentials
        $this->apiContext = new ApiContext(new OAuthTokenCredential($this->client_id, $this->secret));
        $this->apiContext->setConfig(config('paypal.settings'));
    }
    

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\Http\Response      */
    public function showRegistrationForm($sponsorname = null)
    {

// echo "string";
 // session_start();

 // print_r($_SESSION);die();


 // print_r(Session::all());die();
        // dd(354345);

         $sponsor_value = Session::get('replication'); 

         // dd($sponsor_value);
         $block=MenuSettings::where('menu_name','=','Register new')->value('status');
          $active = User::where('username','=',$sponsorname)->value('active');
        if($block == 'no'|| $active == 'no')
          return redirect("/");

        if (property_exists($this, 'registerView')) {
            return view($this->registerView);
        }

        if (User::where('username', '=',  $sponsorname)->count() > 0) {
            $sponsor_name = $sponsorname;  
        }elseif(Session::has('replication')) {
          
            User::where('username', '=', trim(Session::get('replication')))->count();
            $sponsor_name=Session::get('replication');          
        }
        else{
            
            $sponsor_name = NULL;

             //return redirect("/");
            //$sponsor_name = User::find(2)->username;

        }
        $user_id=User::where('username',$sponsor_name)->value('id');
        $spon_pack=ProfileModel::where('user_id',$user_id)->value('package');

        
        if($spon_pack == 1 && $user_id > 1 ){
             Session::flash('flash_notification', array('level' => 'danger', 'message' =>'Ooopzz...!! Sory, Please purchase any package to proceed with registration'));
                return redirect()->route('login');
        }

        



        $location = GeoIP::getLocation();
        $ip_latitude = $location['lat'];
        $ip_longtitude = $location['lon'];
        $oldcountries = CountryState::getCountries();
            // dd($oldcountries);
            $countries=[];
            foreach ($oldcountries as $key => $country) {
               if($key <> 'PS' && $key <> 'US')
                $countries[$key]=$country;
             }

        $states = CountryState::getStates('IL');
        $leg = 'L';
        $placement_user ='admin';
        $country = Country::all();
        $package = Packages::all();
        $joiningfee = Settings::value('joinfee');
        $voucher_code=Voucher::pluck('voucher_code');
        $payment_type=PaymentType::where('status','yes')->get();
        $transaction_pass=self::RandomString();
        $app = AppSettings::find(1);
        $currency_sy = $app->currency;
        // dd($profile_photo);

        $sponsor = NULL;
        $profile = NULL;
        $profile_photo = NULL;

        if($sponsor_name != NULL){

            $sponsor = User::where('username','=',$sponsor_name)->get();
            $profile = ProfileInfo::where('user_id','=',$sponsor[0]->id)->get();
            $profile_photo = $profile[0]->profile;
            
            if (!Storage::disk('images')->exists($profile_photo)){
                $profile_photo = 'avatar-big.png';
            }
            if(!$profile_photo){
                $profile_photo = 'avatar-big.png';
            }
        }

// dd($profile_photo);
        

      


        return view('auth.register',compact('sponsor_name','countries','states','ip_latitude','ip_longtitude','leg','placement_user','package','transaction_pass','package','sponsorname','sponsor','profile','profile_photo','currency_sy','payment_type','joiningfee'));

    }

     public function RandomString()
        {
                    $characters = "23456789abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ";
                    $charactersLength = strlen($characters);
                    $randstring = '';
                    for ($i = 0; $i < 11; $i++) {
                        $randstring .= $characters[rand(0, $charactersLength - 1)];
                    }
                    return $randstring;
        }



    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {


        return Validator::make($data, [

            //Login information
            'sponsor_name' => 'exists:users,username',
            'username' => 'required|max:255|unique:pending_transactions',
            'email' => 'required|max:255|unique:pending_transactions',
            'username' => 'required|max:255|unique:users',
            'password' => 'required|min:6',
            
            // Network information
            // 'role_id' => 'required',
            // 'rank_id' => 'required|max:255',
            // 'sponsor_id' => 'required|max:255',
            // 'status' => 'required|max:255',



            //Identification
            'firstname' => 'required|max:255',
            'lastname' => 'max:255',//OPTIONAL
            'gender' => 'required|max:255',  
            // 'date_of_birth' => 'required|max:255',
            // 'job_title' => 'required|max:255',
            'tax_id' => 'max:255', //TAX ID //VAT// National Identification Number //OPTIONAL
     

            //Contact Information
            'country' => 'required|max:255',
            'state' => 'required|max:255',
            'city' => 'required|max:255',
            'post_code' => 'max:255',//OPTIONAL            
            // 'latitude' => 'required|max:255',
            // 'longitude' => 'required|max:255',
            'address' => 'required|max:255',        
            'email' => 'required|email|max:255|unique:users',            
            'phone' => 'max:255',//OPTIONAL
            

            //Media
            // 'profile_photo' => 'required|max:255',
            // 'profile_coverphoto' => 'required|max:255',

            //Social links
            'twitter_username' => 'max:255', //OPTIONAL
            'facebook_username' => 'max:255', //OPTIONAL
            'youtube_username' => 'max:255', //OPTIONAL
            'linkedin_username' => 'max:255', //OPTIONAL
            'pinterest_username' => 'max:255', //OPTIONAL
            'instagram_username' => 'max:255', //OPTIONAL
            'google_username' => 'max:255', //OPTIONAL
            

            //Instant Messaging Ids (IM)
            'skype_username' => 'max:255', //OPTIONAL
            'whatsapp_number' => 'max:255', //OPTIONAL

            //Profile  
            'bio' => 'max:600', //OPTIONAL


        ]);

    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        
        return User::create([

         
            'username' => $data['username'],
            'password' => bcrypt($data['password']),

            //Network Information   
            'role_id' => '2',
            'sponsor_id' => User::findByUsernameOrFail($data['sponsor_name'])->id,
           
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'], 
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'],
            'job_title' => $data['job_title'],
            'tax_id' => $data['tax_id'],
           
            'country' => $data['country'],
            'state' => $data['state'],
            'city' => $data['city'],
            'post_code' => $data['post_code'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'address' => $data['address'],
            'email' => $data['email'],
            'phone' => $data['phone'],

            
            'twitter_username' => $data['twitter_username'],
            'facebook_username' => $data['facebook_username'],
            'youtube_username' => $data['youtube_username'],
            'linkedin_username' => $data['linkedin_username'],
            'pinterest_username' => $data['pinterest_username'],
            'instagram_username' => $data['instagram_username'],
            'google_username' => $data['google_username'],

            //Instant Messaging Ids (IM)
            'skype_username' => $data['skype_username'],
            'whatsapp_number' => $data['whatsapp_number'],

            //Profile  
            'bio' => $data['bio'],



            //App Specific



        ]);
    }


    public function register(Request $request)
    {
        error_log($request);
        error_log($request->payment);
        error_log('detect payment');
        // dd($request->all());
        $validator = $this->validator($request->all());

        if ($validator->fails()) {
            $this->throwValidationException(
                $request, $validator
            );
        }else{

            $data= $request->all();
            $data['reg_type']         = null;
            $data['cpf']              = null;
            $data['passport']         = null;
            $data['location']         = null;
            $data['reg_by']           = 'site';
            $data['package']          = 1;
            Session::put('transaction_pass',$data['transaction_pass']);
            Session::put('password',$data['password']);

            $sponsor_id = User::checkUserAvailable($data['sponsor']);
            
            $placement_id =  $sponsor_id ;
            $data['placement_user'] =$data['sponsor'];
            if (!$sponsor_id) {
                
                return redirect()->back()->withErrors(['The sponsor not exist'])->withInput();
            }
            if (!$placement_id) {
                /**
                 * If placement_id validates as false, redirect back without registering , with errors
                 */
                return redirect()->back()->withErrors(['The sponsor not exist'])->withInput();
            }

            $joiningfee = Settings::value('joinfee');
            $orderid ='Atmor-'. mt_rand();
            if($request->payment == 'cheque' && $joiningfee > 0){ 
                $request->payment='paypal';
            }

            $btc_forwarding = Settings::value('bitcon_address');

            $register=PendingTransactions::create([
                 'order_id' =>$orderid,
                 'username' =>$request->username,
                 'email' =>$request->email,
                 'sponsor' => $sponsor_id,
                 'request_data' =>json_encode($data),
                 'payment_method'=>$request->payment,
                 'payment_type' =>'register',
                 'amount' => $joiningfee,
                ]);
            // dd($joiningfee);
               if($request->payment == 'paypal'){ 
                    
                    Session::put('paypal_id',$register->id);
                    $data = [];
                    $data['items'] = [
                        [
                            'name' => Config('APP_NAME'),
                            'price' => $joiningfee,
                            'qty' => 1
                        ]
                    ];

                    $data['invoice_id'] = time();
                    $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
                    $data['return_url'] = url('/register/paypal/success',$register->id);
                    $data['cancel_url'] = url('register');

                    $total = 0;
                    foreach($data['items'] as $item) {
                        $total += $item['price']*$item['qty'];
                    }

                    $data['total'] = $total; 
                    self::$provider->setCurrency('EUR');
                    $response = self::$provider->setExpressCheckout($data); 
                    PendingTransactions::where('id',$register->id)->update(['payment_data' => json_encode($response),'paypal_express_data' => json_encode($data)]);
                

                    return redirect($response['paypal_link']);
                }

             if($request->payment == 'netpay'){ 
                    
                     Session::put('netpay_id',$register->id);
                    // $data = [];
                    // $data['items'] = [
                    //     [
                    //         'name' => Config('APP_NAME'),
                    //         'price' => $joiningfee,
                    //         'qty' => 1
                    //     ]
                    // ];

                    // $data['invoice_id'] = time();
                    // $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
                    // $data['return_url'] = url('/register/paypal/success',$register->id);
                    // $data['cancel_url'] = url('register');

                    // $total = 0;
                    // foreach($data['items'] as $item) {
                    //     $total += $item['price']*$item['qty'];
                    // }

                    // $data['total'] = $total; 
                    // self::$provider->setCurrency('EUR');
                    // $response = self::$provider->setExpressCheckout($data); 
                    // PendingTransactions::where('id',$register->id)->update(['payment_data' => json_encode($response),'paypal_express_data' => json_encode($data)]);
                
                     $link="https://uiservices.netpay-intl.com/hosted/?merchantID=7687751&url_redirect=https%3a%2f%2fdev.algolight.net%2fnetpay%2fregister&url_notify=&trans_comment=&trans_refNum=&trans_installments=1&trans_amount=20&trans_currency=EUR&disp_paymentType=&disp_payFor=Purchase&disp_recurring=0&disp_lng=en-us&disp_mobile=auto&signature=BU0tL1%2bnTA%2b%2fvImtNs%2bv0PQnYw80CEvYDq4toRPShb4%3d";
		     return redirect($link);
                }

            if($request->payment == 'bitcoin'){


                $title='Bitaps Payment';
                $sub_title='Bitaps Payment';
                $base='Bitaps Payment';
                $method='Bitaps Payment';
                $url ='https://api.bitaps.com/btc/v1//create/payment/address' ;
                $payment_details = $this->url_get_contents($url,[
                                        'forwarding_address'=>$btc_forwarding,
                                        'callback_link'=>url('bitaps/paymentnotify'),
                                        'confirmations'=>3
                                        ]);

                $conversion = $this->url_get_contents('https://api.bitaps.com/market/v1/ticker/btceur',false);
                $package_amount = $joiningfee/$conversion->data->last;
                $package_amount=round($package_amount,8);
                PendingTransactions::where('id',$register->id)->update(['payment_code'=>$payment_details->payment_code,'invoice'=>$payment_details->invoice,'payment_address'=>$payment_details->address,'payment_data'=>json_encode($payment_details)]);
                $trans_id=$register->id;

                return view('auth.bitaps',compact('title','sub_title','base','method','payment_details','data','package_amount','trans_id'));
            }

            if($request->payment == 'cheque'){
                 // return redirect()->action('Auth\RegisterController@banktransferPreview', ['id' =>$register->id]);
              $userresult = User::add($data,$sponsor_id,$placement_id);
                if(!$userresult){
                    return redirect()->back()->withErrors(['Opps something went wrong'])->withInput();
                }
                PendingTransactions::where('id',$register->id)->update(['payment_status' => 'complete']);
                $sponsorname = $data['sponsor'];
                $placement_username = User::find($placement_id)->username;
                $legname = $data['leg'] == "L" ? "Left" : "right";            
                
                Activity::add("Added user $userresult->username","Added $userresult->username sponsor as $sponsorname ");
                Activity::add("Joined as $userresult->username","Joined in system as $userresult->username sponsor as $sponsorname ",$userresult->id);
                
                //test
                // $email = Emails::find(1);
                // $welcome=welcomeemail::find(1);
                // $app_settings = AppSettings::find(1);
               
                // // Mail::send('emails.register',
                // //     ['email'         => $email,
                // //         'company_name'   => $app_settings->company_name,
                // //         'logo'   => $app_settings->logo,
                // //         'firstname'      => $data['firstname'],
                // //         'name'           => $data['lastname'],
                // //         'login_username' => $data['username'],
                // //         'password'       => $data['password'],
                // //         'welcome'        => $welcome,
                // //         'transaction_pass'=>$data['transaction_pass'],
                // //     ], function ($m) use ($data, $email) {
                // //         $m->to($data['email'], $data['firstname'])->subject('Successfully registered')->from($email->from_email, $email->from_name);
                // //     });

                // //test
                //  $template = Mail_template::where('id',1)->value('text');
                //     error_log($template);

                //     $template = str_replace( '{{$firstname}}', $data['firstname'], $template );
                //     $template = str_replace( '{{$lastname}}', $data['lastname'], $template );
                //     $template = str_replace( '{{$username}}', $data['username'], $template );
                //     $template = str_replace( '{{$password}}', $data['password'], $template );
                //     $template = str_replace( '{{$transaction_pass}}', $data['transaction_pass'], $template );
                //     Mail::send('emails.welcome',
                //         ['email'         => $email,
                //             'template'       => $template,
                //             'company_name'   => $app_settings->company_name,
                //             'logo'   => $app_settings->logo,
                //             'firstname'      => $data['firstname'],
                //             'lname'           => $data['lastname'],
                //             'login_username' => $data['username'],
                //             'password'       => $data['password'],
                //             'welcome'        => $welcome,
                //             'transaction_pass'=>$data['transaction_pass'],
                //         ], function ($m) use ($data, $email) {
                //             $m->to($data['email'], $data['firstname'])->subject('Successfully registered')->from($email->from_email, $email->from_name);
                //         });
                //     //test

                //     //sponsormail
                //     $template1 = Mail_template::where('id',3)->value('text');
                //     error_log($template);
                //     $sponsor_mail=User::where('id',$sponsor_id)->value('email');
                //     $template1 = str_replace( '{{$firstname}}', $data['firstname'], $template1 );
                //     $template1 = str_replace( '{{$lastname}}', $data['lastname'], $template1 );
                //     $template1 = str_replace( '{{$username}}', $data['username'], $template1 );
                //     $template1 = str_replace( '{{$email}}', $data['email'], $template1 );
                //     $template1 = str_replace( '{{$sponsorname}}', $sponsorname, $template1 );

                //      Mail::send('emails.welcome',
                //         [
                //           'template'       => $template1, 
                //         ], function ($m) use ($sponsor_mail,$email,$sponsorname) {
                //             $m->to($sponsor_mail, $sponsorname)->subject('New user register under Your name')->from($email->from_email, $email->from_name);
                //         });

                //     $allusers = User::all();
                //     foreach($allusers as $user){
                //         if(($user->email != $sponsor_mail) && ($user->email != $data['email'])){
                //             $userselect_email = $user->email;
                //             $userselect_firstname = $user->name;
                //             $userselect_lastname = $user->lastname;
                //             $userselect_username = $user->username;

                //             $template3 = Mail_template::where('id',4)->value('text');
                //             error_log($template);
                //             //$sponsor_mail=User::where('username',$sponsorname)->value('email');
                //             $template3 = str_replace( '{{$newuser_firstname}}', $data['firstname'], $template3 );
                //             $template3 = str_replace( '{{$newuser_lastname}}', $data['lastname'], $template3 );
                //             $template3 = str_replace( '{{$newuser_username}}', $data['username'], $template3 );
                //             $template3 = str_replace( '{{$newuser_email}}', $data['email'], $template3 );
                //             $template3 = str_replace( '{{$firstname}}', $userselect_firstname, $template3 );
                //             $template3 = str_replace( '{{$lastname}}', $userselect_lastname, $template3 );

                //              Mail::send('emails.welcome',
                //                 [
                //                   'template'       => $template3, 
                //                 ], function ($m) use ($userselect_email,$email,$userselect_username) {
                //                     $m->to($userselect_email, $userselect_username)->subject('New user Entry')->from($email->from_email, $email->from_name);
                //                 });
                //         }
                //     }
                    //sponsormail

Log::debug('Register Controller Auth - Arslan');
                return redirect("register/preview/" . Crypt::encrypt($userresult->id));
            }
            if($request->payment == 'bank'){


                $title     = "Payment Details";
                $sub_title = "Payment Details";
                $base      = "Payment Details";
                $method    = "Payment Details";
              //  $data=PendingTransactions::find($request->id);

                $trans_id = $register->id;

                return view('auth.bankpaydetails',compact('title', 'sub_title', 'base', 'method','joiningfee','trans_id'));
            }
        }

       
    }

    public function netpayReg(Request $request){
        $payment_id = Session::get('netpay_id');

        error_log("test pay");
        error_log($request->replyDesc);
        error_log("test netpay");
 
        if($request->replyDesc == "SUCCESS"){
            $item = PendingTransactions::where('id',$payment_id)->first();
            $details=json_decode($item->request_data,true);
            $sponsor_id = $item->sponsor;
            $userresult = User::add($details,$sponsor_id,$sponsor_id);
            //$sponsorname = $userresult->sponsor;
            $sponsorname=User::where('id',$sponsor_id)->value('username');
            error_log($sponsorname);
            error_log($sponsor_id);
            error_log(json_encode($item));
            error_log("check sponsor");
          Activity::add("Added user $userresult->username","Added $userresult->username sponsor as $sponsorname ");
                Activity::add("Joined as $userresult->username","Joined in system as $userresult->username sponsor as $sponsorname ",$userresult->id);
            //  $email = Emails::find(1);
            // $welcome=welcomeemail::find(1);
            // $app_settings = AppSettings::find(1);

            // $template = Mail_template::where('id',1)->value('text');
            // error_log($template);

            // $template = str_replace( '{{$firstname}}', $details['firstname'], $template );
            // $template = str_replace( '{{$lastname}}', $details['lastname'], $template );
            // $template = str_replace( '{{$username}}', $details['username'], $template );
            // $template = str_replace( '{{$password}}', $details['password'], $template );
            // $template = str_replace( '{{$transaction_pass}}', $details['transaction_pass'], $template );
            // Mail::send('emails.welcome',
            //     ['email'         => $email,
            //         'template'       => $template,
            //         'company_name'   => $app_settings->company_name,
            //         'logo'   => $app_settings->logo,
            //         'firstname'      => $details['firstname'],
            //         'lname'           => $details['lastname'],
            //         'login_username' => $details['username'],
            //         'password'       => $details['password'],
            //         'welcome'        => $welcome,
            //         'transaction_pass'=>$details['transaction_pass'],
            //     ], function ($m) use ($details, $email) {
            //         $m->to($details['email'], $details['firstname'])->subject('Successfully registered')->from($email->from_email, $email->from_name);
            //     });
            // $template1 = Mail_template::where('id',3)->value('text');
            // error_log($template);

            // $sponsor_mail=User::where('id',$sponsor_id)->value('email');
            // error_log($sponsor_mail);
            // error_log("check mail");
            // $template1 = str_replace( '{{$firstname}}', $details['firstname'], $template1 );
            // $template1 = str_replace( '{{$lastname}}', $details['lastname'], $template1 );
            // $template1 = str_replace( '{{$username}}', $details['username'], $template1 );
            // $template1 = str_replace( '{{$email}}', $details['email'], $template1 );
            // $template1 = str_replace( '{{$sponsorname}}', $sponsorname, $template1 );

            //  Mail::send('emails.welcome',
            //     [
            //       'template'       => $template1, 
            //     ], function ($m) use ($sponsor_mail,$email,$sponsorname) {
            //         $m->to($sponsor_mail, $sponsorname)->subject('New user register under Your name')->from($email->from_email, $email->from_name);
            //     });

            // $allusers = User::all();
            // foreach($allusers as $user){
            //     if(($user->email != $sponsor_mail) && ($user->email != $details['email'])){
            //         $userselect_email = $user->email;
            //         $userselect_firstname = $user->name;
            //         $userselect_lastname = $user->lastname;
            //         $userselect_username = $user->username;

            //         $template3 = Mail_template::where('id',4)->value('text');
            //         error_log($template);
            //         //$sponsor_mail=User::where('username',$sponsorname)->value('email');
            //         $template3 = str_replace( '{{$newuser_firstname}}', $details['firstname'], $template3 );
            //         $template3 = str_replace( '{{$newuser_lastname}}', $details['lastname'], $template3 );
            //         $template3 = str_replace( '{{$newuser_username}}', $details['username'], $template3 );
            //         $template3 = str_replace( '{{$newuser_email}}', $details['email'], $template3 );
            //         $template3 = str_replace( '{{$firstname}}', $userselect_firstname, $template3 );
            //         $template3 = str_replace( '{{$lastname}}', $userselect_lastname, $template3 );

            //          Mail::send('emails.welcome',
            //             [
            //               'template'       => $template3, 
            //             ], function ($m) use ($userselect_email,$email,$userselect_username) {
            //                 $m->to($userselect_email, $userselect_username)->subject('New user Entry')->from($email->from_email, $email->from_name);
            //             });
            //     }
            //}

           return redirect("register/preview/" . Crypt::encrypt($userresult->id)); 
           
        } else {
           Session::flash('flash_notification', array('level' => 'danger', 'message' => "Payment failed"));
           return redirect("/register");
        }
    }
   
    public function paypalReg(Request $request){

        $payment_id = Session::get('paypal_payment_id');
        $temp_data=PaypalDetails::where('token','=',$payment_id)->first();
        $data=json_decode($temp_data->regdetails,true);
        
        Session::forget('paypal_payment_id');
        if (empty(Input::get('PayerID')) || empty(Input::get('token'))) {

            Session::flash('flash_notification', array('level' => 'danger', 'message' => "Payment failed"));

             return redirect("/register");
        }
        $payment = Payment::get($payment_id, $this->_api_context);
        $execution = new PaymentExecution();
        $execution->setPayerId(Input::get('PayerID'));

        $result = $payment->execute($execution, $this->_api_context);
        
        if ($result->getState() == 'approved') { 

            $sponsor_id = User::checkUserAvailable($data['sponsor']);
            $placement_id =  User::checkUserAvailable($data['placement_user']);

            $userresult = User::add($data,$sponsor_id,$placement_id);
            if(!$userresult)
                return redirect('user/register')->withErrors(['Opps something went wrong'])->withInput();
             $userPackage = Packages::find($data['package']);
            $sponsorname = $userresult->sponsor ? $userresult->sponsor : $data['sponsor'];
            $placement_username = User::find($placement_id)->username;
            $legname = $data['leg'] == "L" ? "Left" : "right";            
            Activity::add("Added user $userresult->username","Added $userresult->username sponsor as $sponsorname ");
            Activity::add("Joined as $userresult->username","Joined in system as $userresult->username sponsor as $sponsorname ",$userresult->id);
            Activity::add("Package purchased","Purchased package - $userPackage->package ",$userresult->id);
            // $email = Emails::find(1);
            // $welcome=welcomeemail::find(1);
            // $app_settings = AppSettings::find(1);
            // Mail::send('emails.register',
            //    ['email'         => $email,
            //         'company_name'   => $app_settings->company_name,
            //         'firstname'      => $data['firstname'],
            //         'name'           => $data['lastname'],
            //         'login_username' => $data['username'],
            //         'password'       => $data['password'],
            //         'welcome'        =>$welcome,
            //         'transaction_pass'=>$data['transaction_pass'],
            //     ], function ($m) use ($data, $email) {
            //         $m->to($data['email'], $data['firstname'])->subject('Successfully registered')->from($email->from_email, $email->from_name);
            //     });

            // $template1 = Mail_template::where('id',3)->value('text');
            // error_log($template);
            // $sponsor_mail=User::where('username',$sponsorname)->value('email');
            // $template1 = str_replace( '{{$firstname}}', $details['firstname'], $template1 );
            // $template1 = str_replace( '{{$lastname}}', $details['lastname'], $template1 );
            // $template1 = str_replace( '{{$username}}', $details['username'], $template1 );
            // $template1 = str_replace( '{{$email}}', $details['email'], $template1 );
            // $template1 = str_replace( '{{$sponsorname}}', $sponsorname, $template1 );

            //  Mail::send('emails.welcome',
            //     [
            //       'template'       => $template1, 
            //     ], function ($m) use ($sponsor_mail,$email,$sponsorname) {
            //         $m->to($sponsor_mail, $sponsorname)->subject('New user register under Your name')->from($email->from_email, $email->from_name);
            //     });

            return redirect("register/preview/" . Crypt::encrypt($userresult->id)); 
            }
        Session::flash('flash_notification', array('level' => 'danger', 'message' => "Payment failed"));
         return redirect("/register");
        }

    public function preview($idencrypt)
    {
        $title     = trans('register.registration');
        $sub_title = trans('register.preview');
        $method    = trans('register.preview');
        $base      = trans('register.preview');
// echo Crypt::decrypt($idencrypt) ;
// die();
        $userresult      = User::with(['profile_info', 'profile_info.package_detail', 'sponsor_tree', 'tree_table', 'purchase_history.package'])->find(Crypt::decrypt($idencrypt));
        error_log("user details");
        error_log(json_encode($userresult));
        error_log(json_encode($userresult->id));
        $transaction_pass =  Session::get('transaction_pass');
        $password = Session::get('password');
        error_log($transaction_pass);
        error_log($password);

        $userCountry = $userresult->profile_info->country;
        if ($userCountry) {
            $countries = CountryState::getCountries();
            $country   = array_get($countries, $userCountry);
        } else {
            $country = "A downline";
        }
        $userState = $userresult->profile_info->state;
        if ($userState) {
            $states = CountryState::getStates($userCountry);
            $state  = array_get($states, $userState);
        } else {
            $state = "unknown";
        }

        $sponsorId       = $userresult->sponsor_tree->sponsor;
        $sponsorUserName = User::find($sponsorId)->username;
        //  $leg = Tree_Table::where('user_id','=',$userresult->id)->value('leg');
        if ($userresult) {

            //mail

            $email = Emails::find(1);
            $welcome=welcomeemail::find(1);
            $app_settings = AppSettings::find(1);

            $template = Mail_template::where('id',1)->value('text');
            error_log($template);
            $template = str_replace( '{{$firstname}}', $userresult->name, $template );
            $template = str_replace( '{{$lastname}}', $userresult->lastname, $template );
            $template = str_replace( '{{$username}}', $userresult->username, $template );
            $template = str_replace( '{{$password}}', $password, $template );
            $template = str_replace( '{{$transaction_pass}}', $transaction_pass, $template );
            Mail::send('emails.welcome',
                [
                  'template'       => $template, 
                ], function ($m) use ($userresult, $email) {
                    $m->to($userresult->email, $userresult->name)->subject('Successfully registered')->from($email->from_email, $email->from_name);
                });
            $template1 = Mail_template::where('id',3)->value('text');
            error_log($template);
            $sponsor_mail=User::where('username',$sponsorUserName)->value('email');
            $template1 = str_replace( '{{$firstname}}', $userresult->name, $template1 );
            $template1 = str_replace( '{{$lastname}}', $userresult->lastname, $template1 );
            $template1 = str_replace( '{{$username}}', $userresult->username, $template1 );
            $template1 = str_replace( '{{$email}}', $userresult->email, $template1 );
            $template1 = str_replace( '{{$sponsorname}}', $sponsorUserName, $template1 );

             Mail::send('emails.welcome',
                [
                  'template'       => $template1, 
                ], function ($m) use ($sponsor_mail,$email,$sponsorUserName) {
                    $m->to($sponsor_mail, $sponsorUserName)->subject('New user register under Your name')->from($email->from_email, $email->from_name);
                });

            $allusers = User::all();
            foreach($allusers as $user){
                //if(($user->email != $sponsor_mail) && ($user->email != $userresult->email)){
                if($user->email == "bluesky410219@gmail.com"){
                    $userselect_email = $user->email;
                    $userselect_firstname = $user->name;
                    $userselect_lastname = $user->lastname;
                    $userselect_username = $user->username;

                    $template3 = Mail_template::where('id',4)->value('text');
                    error_log($template);
                    //$sponsor_mail=User::where('username',$sponsorname)->value('email');
                    $template3 = str_replace( '{{$newuser_firstname}}', $userresult->name, $template3 );
                    $template3 = str_replace( '{{$newuser_lastname}}', $userresult->lastname, $template3 );
                    $template3 = str_replace( '{{$newuser_username}}', $userresult->username, $template3 );
                    $template3 = str_replace( '{{$newuser_email}}', $userresult->email, $template3 );
                    $template3 = str_replace( '{{$firstname}}', $userselect_firstname, $template3 );
                    $template3 = str_replace( '{{$lastname}}', $userselect_lastname, $template3 );

                     Mail::send('emails.welcome',
                        [
                          'template'       => $template3, 
                        ], function ($m) use ($userselect_email,$email,$userselect_username) {
                            $m->to($userselect_email, $userselect_username)->subject('New user Entry')->from($email->from_email, $email->from_name);
                        });
                }
            }
            //mail


            // dd($user);
            return view('auth.preview', compact('title', 'sub_title', 'method', 'base', 'userresult', 'country', 'state', 'sub_title','sponsorUserName'));
        } else {
            return redirect()->back();
        }
    }

    public function paypalRegSuccess(Request $request,$id){

        // dd($request->all());
        // echo "here";
          self::$provider->setCurrency('EUR');
          $response = self::$provider->getExpressCheckoutDetails($request->token);

          // dd($?response);
          $item = PendingTransactions::find($id);
          $item->payment_response_data = json_encode($response);
          $express_data=json_decode($item->paypal_express_data,true);

          // dd($express_data['invoice_id']);
          // $response = self::$provider->doExpressCheckoutPayment($express_data, $request->token, $request->PayerID);
          $item->paypal_recurring_reponse = json_encode($response);
          $item->save();
          if($response['ACK'] == 'Success'){

            /*added vincy*/

            $item->payment_status='payment_initiated';
            $item->save();
            $data = [];
            $data['items'] = [
                        [
                            'name' => Config('APP_NAME'),
                            'price' => $item->amount,
                            'qty' => 1
                        ]
                    ];

            $data['invoice_id'] = $express_data['invoice_id'];
            $data['invoice_description'] = "Order #{$express_data['invoice_id']} Invoice";
            $data['return_url'] = url('/register/paypal/success',$id);
            $data['cancel_url'] = url('register');

            $item->save();
                
            $total = 0;
            foreach($data['items'] as $item) {
                        $total += $item['price']*$item['qty'];
            }

            $data['total'] = $total;

            $response = self::$provider->doExpressCheckoutPayment($data, $request->token, $request->PayerID);

            $item = PendingTransactions::find($id);
            $item->payment_doexpressresponse_data=json_encode($response);
            $item->save();
            /**/
            

            
            // dd($response);

            if($response['ACK'] == 'Success'){
                $item = PendingTransactions::find($id);
                error_log(json_encode($item));
                error_log('item display');
                $item->payment_status='complete';
                $item->save();
                $details=json_decode($item->request_data,true);
                error_log(json_encode($details));
                error_log('detail display');
                $username=User::where('username',$item->username)->value('id');
                $email=User::where('email',$item->email)->value('id');
                if($username == null && $email == null){
                    $userresult = User::add($details,$item->sponsor,$item->sponsor);
                    $sponsorname = $details['sponsor'];
                    $legname = $details['leg'] == "L" ? "Left" : "right";            
                    
                    Activity::add("Added user $userresult->username","Added $userresult->username sponsor as $sponsorname ");
                    Activity::add("Joined as $userresult->username","Joined in system as $userresult->username sponsor as $sponsorname ",$userresult->id);
                    // $email = Emails::find(1);
                    // $welcome=welcomeemail::find(1);
                    // $app_settings = AppSettings::find(1);
       
                    // $template = Mail_template::where('id',1)->value('text');
                    // error_log($template);
                    // $template = str_replace( '{{$firstname}}', $details['firstname'], $template );
                    // $template = str_replace( '{{$lastname}}', $details['lastname'], $template );
                    // $template = str_replace( '{{$username}}', $details['username'], $template );
                    // $template = str_replace( '{{$password}}', $details['password'], $template );
                    // $template = str_replace( '{{$transaction_pass}}', $details['transaction_pass'], $template );
                    // Mail::send('emails.welcome',
                    //     [
                    //       'template'       => $template, 
                    //     ], function ($m) use ($details, $email) {
                    //         $m->to($details['email'], $details['firstname'])->subject('Successfully registered')->from($email->from_email, $email->from_name);
                    //     });
                    // $template1 = Mail_template::where('id',3)->value('text');
                    // error_log($template);
                    // $sponsor_mail=User::where('username',$sponsorname)->value('email');
                    // $template1 = str_replace( '{{$firstname}}', $details['firstname'], $template1 );
                    // $template1 = str_replace( '{{$lastname}}', $details['lastname'], $template1 );
                    // $template1 = str_replace( '{{$username}}', $details['username'], $template1 );
                    // $template1 = str_replace( '{{$email}}', $details['email'], $template1 );
                    // $template1 = str_replace( '{{$sponsorname}}', $sponsorname, $template1 );

                    //  Mail::send('emails.welcome',
                    //     [
                    //       'template'       => $template1, 
                    //     ], function ($m) use ($sponsor_mail,$email,$sponsorname) {
                    //         $m->to($sponsor_mail, $sponsorname)->subject('New user register under Your name')->from($email->from_email, $email->from_name);
                    //     });

                    // $allusers = User::all();
                    // foreach($allusers as $user){
                    //     if($user->email != $sponsor_mail){
                    //         $userselect_email = $user->email;
                    //         $userselect_firstname = $user->name;
                    //         $userselect_lastname = $user->lastname;
                    //         $userselect_username = $user->username;

                    //         $template3 = Mail_template::where('id',4)->value('text');
                    //         error_log($template);
                    //         //$sponsor_mail=User::where('username',$sponsorname)->value('email');
                    //         $template3 = str_replace( '{{$newuser_firstname}}', $details['firstname'], $template3 );
                    //         $template3 = str_replace( '{{$newuser_lastname}}', $details['lastname'], $template3 );
                    //         $template3 = str_replace( '{{$newuser_username}}', $details['username'], $template3 );
                    //         $template3 = str_replace( '{{$newuser_email}}', $details['email'], $template3 );
                    //         $template3 = str_replace( '{{$firstname}}', $userselect_firstname, $template3 );
                    //         $template3 = str_replace( '{{$lastname}}', $userselect_lastname, $template3 );

                    //          Mail::send('emails.welcome',
                    //             [
                    //               'template'       => $template3, 
                    //             ], function ($m) use ($userselect_email,$email,$userselect_username) {
                    //                 $m->to($userselect_email, $userselect_username)->subject('New user Entry')->from($email->from_email, $email->from_name);
                    //             });
                    //     }
                    // }

                    // Mail::send('emails.sponsoremail',
                    // ['email'         => $email,
                    //     'sponsor_per' => $sponsor_per,
                    //     'company_name'   => $app_settings->company_name,
                    //     'logo'   => $app_settings->logo,
                    //     'username'      => $sponsorname,
                    //     'newuser' => $details['username'],
                    //     'email'          => $details['email'],
                    // ], function ($m) use ($details, $email,$sponsor_per) {
                    //     $m->to($sponsor_per, $details['firstname'])->subject('Alert for new user')->from($email->from_email, $email->from_name);
                    // });

             
                     return redirect("register/preview/" . Crypt::encrypt($userresult->id));
                  }
                  else{
                      Session::flash('flash_notification', array('level' => 'error', 'message' => 'User Already Exist'));
                      return Redirect::to('register');

                  }
             }else{
                return redirect('register')->withErrors(['Opps something went wrong']);
             } 
          }
          else{
              Session::flash('flash_notification', array('level' => 'error', 'message' => 'Error In payment'));
                return Redirect::to('register');
          }

        
            // dd($details['firstname']);

           
          // }
          // else{
          //    Session::flash('flash_notification', array('level' => 'danger', 'message' => trans('register.error_in_payment')));
          //   return Redirect::to('login');
          // }

    }

    public function banktransferPreview(Request $request){
    
        $title     = "Payment Details";
        $sub_title = "Payment Details";
        $base      = "Payment Details";
        $method    = "Payment Details";
        $bank_details = ProfileInfo::where('user_id',1)->first();
        $data=PendingTransactions::find($request->id);
        $joiningfee=$data->amount;
        $orderid=$data->order_id;
        $trans_id=$request->id;
    Session::flash('flash_notification', array('level' => 'success', 'message' => "The account will be activated once the payment has been processed!"));
    return view('auth.bankpaydetails',compact('title', 'sub_title', 'base', 'method','orderid','bank_details','joiningfee','trans_id'));

}

public function checkStatus($trans){
    

    $item = PendingTransactions::where('id',$trans)->first();

    
    if (is_null($item)) {
    return response()->json(['valid' => false]);
  }elseif($item->payment_status == 'complete'){
        
        $user_id=User::where('username',$item->username)->value('id');
        if($user_id <> null){
        return response()->json(['valid' => true,'status'=>$item->payment_status,'id'=>Crypt::encrypt($user_id)]);
      }
    }else{
         return response()->json(['valid' => true,'status'=>$item->payment_status,'id'=>null]);
        
  }
    
    return response()->json(['valid' => false]);
}


        function url_get_contents ($Url,$params) {
        if (!function_exists('curl_init')){ 
            die('CURL is not installed!');
        }
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if($params){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($params));
        }

          

         $output = curl_exec($ch);

        curl_close($ch);
        return  json_decode($output);
        }

    public function bitaps(Request $request,$paymentid)
    {

        // dd($request->all());
        $item = PendingTransactions::where('id',$paymentid)->first();
        if (is_null($item)) {
          return response()->json(['valid' => false]);
        }elseif($item->payment_status == 'complete'){
          // dd($item->username);
          $user_id=User::where('username',$item->username)->value('id');
            if($user_id <> null){
                if($item->payment_type == 'upgrade'){
                   return response()->json(['valid' => true,'status'=>$item->payment_status,'id'=>Crypt::encrypt($item->purchase_id)]); 
                }else{
              return response()->json(['valid' => true,'status'=>$item->payment_status,'id'=>Crypt::encrypt($user_id)]);
          }
          }
        }else{
             return response()->json(['valid' => true,'status'=>$item->payment_status,'id'=>null]);
       }
        return response()->json(['valid' => false]);


    }

       public function bitapssuccess(Request $request){
      // dd($request->all());
         $item = PendingTransactions::where('payment_code',$request->code)->first();
         // dd($item);
         if($request->confirmations >=3 && $item->payment_status == 'pending'){
            $item->payment_response_data = json_encode($request->all());
            $item->save();

            $details=json_decode($item->request_data,true);
            $username=User::where('username',$item->username)->value('id');
            $email=User::where('email',$item->email)->value('id');
              if($username == null && $email == null){
                 $userresult = User::add($details,$item->sponsor,$item->sponsor);
                 $item->payment_status ='complete';
                 $item->save();
                 $sponsorname = $details['sponsor'];
                 $legname = $details['leg'] == "L" ? "Left" : "right";            
                
                 Activity::add("Added user $userresult->username","Added $userresult->username sponsor as $sponsorname ");
                 Activity::add("Joined as $userresult->username","Joined in system as $userresult->username sponsor as $sponsorname ",$userresult->id);
                //test

                //  $email = Emails::find(1);
                //  $welcome=welcomeemail::find(1);
                //  $app_settings = AppSettings::find(1);

                // $template = Mail_template::where('id',1)->value('text');
                // error_log($template);

                // $template = str_replace( '{{$firstname}}', $details['firstname'], $template );
                // $template = str_replace( '{{$lastname}}', $details['lastname'], $template );
                // $template = str_replace( '{{$username}}', $details['username'], $template );
                // $template = str_replace( '{{$password}}', $details['password'], $template );
                // $template = str_replace( '{{$transaction_pass}}', $details['transaction_pass'], $template );
                // Mail::send('emails.welcome',
                //     ['email'         => $email,
                //         'template'       => $template,
                //         'company_name'   => $app_settings->company_name,
                //         'logo'   => $app_settings->logo,
                //         'firstname'      => $details['firstname'],
                //         'lname'           => $details['lastname'],
                //         'login_username' => $details['username'],
                //         'password'       => $details['password'],
                //         'welcome'        => $welcome,
                //         'transaction_pass'=>$details['transaction_pass'],
                //     ], function ($m) use ($details, $email) {
                //         $m->to($details['email'], $details['firstname'])->subject('Successfully registered')->from($email->from_email, $email->from_name);
                //     });

                // //test

                // //sponsormail

                // $template1 = Mail_template::where('id',3)->value('text');
                
                // $sponsor_mail=User::where('username',$sponsorname)->value('email');
                // $template1 = str_replace( '{{$firstname}}', $details['firstname'], $template1 );
                // $template1 = str_replace( '{{$lastname}}', $details['lastname'], $template1 );
                // $template1 = str_replace( '{{$username}}', $details['username'], $template1 );
                // $template1 = str_replace( '{{$email}}', $details['email'], $template1 );
                // $template1 = str_replace( '{{$sponsorname}}', $sponsorname, $template1 );

                //  Mail::send('emails.welcome',
                //     [
                //       'template'       => $template1, 
                //     ], function ($m) use ($sponsor_mail,$email,$sponsorname) {
                //         $m->to($sponsor_mail, $sponsorname)->subject('New user register under Your name')->from($email->from_email, $email->from_name);
                //     });

                //  $allusers = User::all();
                // foreach($allusers as $user){
                //     if(($user->email != $sponsor_mail) && ($user->email != $details['email'])){
                //         $userselect_email = $user->email;
                //         $userselect_firstname = $user->name;
                //         $userselect_lastname = $user->lastname;
                //         $userselect_username = $user->username;

                //         $template3 = Mail_template::where('id',4)->value('text');
                //         error_log($template);
                //         //$sponsor_mail=User::where('username',$sponsorname)->value('email');
                //         $template3 = str_replace( '{{$newuser_firstname}}', $details['firstname'], $template3 );
                //         $template3 = str_replace( '{{$newuser_lastname}}', $details['lastname'], $template3 );
                //         $template3 = str_replace( '{{$newuser_username}}', $details['username'], $template3 );
                //         $template3 = str_replace( '{{$newuser_email}}', $details['email'], $template3 );
                //         $template3 = str_replace( '{{$firstname}}', $userselect_firstname, $template3 );
                //         $template3 = str_replace( '{{$lastname}}', $userselect_lastname, $template3 );

                //          Mail::send('emails.welcome',
                //             [
                //               'template'       => $template3, 
                //             ], function ($m) use ($userselect_email,$email,$userselect_username) {
                //                 $m->to($userselect_email, $userselect_username)->subject('New user Entry')->from($email->from_email, $email->from_name);
                //             });
                //     }
                // }
                //sponsormail

              }
         }

         dd("done");
       }

       public function purchaseBitaps(Request $request){

         $item = PendingTransactions::where('payment_code',$request->code)->first();
         if($request->confirmations >=3 && $item->payment_status == 'pending'){
            $item->payment_response_data = json_encode($request->all());
            $item->payment_status='complete';
            $item->save();

            $old_package=ProfileModel::where('user_id',$item->user_id)->value('package');

             if($old_package > 1){
               $cur_pack_order=PendingTransactions::where('user_id',$item->user_id)->where('package',$old_package)->where('payment_status','complete')->first();
               if($cur_pack_order->payment_method == 'paypal'){
                 $agreement = new \PayPal\Api\Agreement();
                $agreementId = $cur_pack_order->paypal_agreement_id;                 
                $agreement = new Agreement();  
                $agreement->setId($agreementId);
                $agreementStateDescriptor = new AgreementStateDescriptor();
                $agreementStateDescriptor->setNote("Cancel the agreement");

                try {
                    $agreement->cancel($agreementStateDescriptor, $this->apiContext);
                    $cancelAgreementDetails = Agreement::get($agreement->getId(), $this->apiContext); 
                          
                } catch (Exception $ex) {                  
                }
             }
           }
            $package=Packages::find($item->package);

            //test

            // $email = Emails::find(1);
            // $template = Mail_template::where('id',2)->value('text');
            // $app_settings = AppSettings::find(1);
            // error_log("detect upgrade");
            //  //error_log($item);
            // $payment_num = "New User";
            // if($item->package == 2) $payment_num = "bronze";
            // if($item->package == 3) $payment_num = "silver";
            // if($item->package == 4) $payment_num = "gold";
            // if($item->package == 5) $payment_num = "diamond";

            // $template = str_replace( '{{$username}}', $item->username, $template );
            // $template = str_replace( '{{$purchase_type}}', $payment_num, $template );
            // $template = str_replace( '{{$pay_type}}', $item->payment_period, $template );
            
            // Mail::send('emails.welcome',
            // [
            //   'template' => $template,
            // ], function ($m) use ($item, $email) {
            //     $m->to($item->email,$item->username)->subject('Successfully Purchase the package.')->from($email->from_email, $email->from_name);
            // });

            //test

            $purchase_id= PurchaseHistory::create([
                            'user_id'=>$item->user_id,
                            'purchase_user_id'=>$item->user_id,
                            'package_id'=>$item->package,
                            'count'=>1,
                            'pv'=>$package->pv,
                            'total_amount'=>$item->amount,
                            'pay_by'=>$item->payment_method,
                            'rs_balance'=>$package->rs,
                            'sales_status'=>'yes',
                          ]);
            /*edited by vincy on match 13 2020*/
            
            $check_in_matrix = Tree_Table::where('user_id',$item->user_id)->where('type','yes')->count();
            if($check_in_matrix == 0){
                 Packages::DirectReferrals($item->user_id,$item->package);
                $addtomatrixplan = Packages::Addtomatrixplan($item->user_id);   
            }
            /*edited by vincy on match 13 2020*/
              RsHistory::create([
                'user_id'=>$item->user_id,                   
                'from_id'=>$item->user_id,
                'rs_credit'=>$package->rs,
              ]);
  
         //commsiiom
            $sponsor_id=Sponsortree::where('user_id',$item->user_id)->value('sponsor');
            if($old_package == 1){
                $pur_count=User::where('id',$sponsor_id)->value('purchase_count');
                $new_pur_count=$pur_count+1;
                User::where('id',$sponsor_id)->update(['purchase_count' => $new_pur_count]);
             }
             ProfileModel::where('user_id',$item->user_id)->update(['package' => $item->package]);
             User::where('id',$item->user_id)->update(['active_purchase' => 'yes']);
            $user_arrs=[];
            $results=Ranksetting::getTreeUplinePackage($item->user_id,1,$user_arrs);
            array_push($results, $item->user_id);
          
            foreach ($results as $key => $value) {
                Packages::rankCheck($value);
            }
            Packages::levelCommission($item->user_id,$item->amount,$item->package);
             $category_update=User::categoryUpdate($sponsor_id);
            // Packages::directReferral($sponsor_id,$item->user_id,$package->amount);
            //comm

            $pur_user=PurchaseHistory::find($purchase_id->id);
            $user=User::join('profile_infos','profile_infos.user_id','=','users.id')
                       ->join('packages','packages.id','=','profile_infos.package')
                       ->where('users.id',$pur_user->user_id)
                       ->select('users.username','users.name','users.lastname','users.email','profile_infos.mobile','profile_infos.address1','packages.package')
                       ->get();
             $userpurchase=array();      
             $userpurchase['name']=$user[0]->name;
             $userpurchase['lastname']=$user[0]->lastname;
             $userpurchase['amount']=$item->amount;
             $userpurchase['payment_method']=$purchase_id->pay_by;
             $userpurchase['mail_address']=$user[0]->email;;
             $userpurchase['mobile']=$user[0]->mobile;
             $userpurchase['address']=$user[0]->address1;
             $userpurchase['invoice_id'] ='0000'.$purchase_id->id;
             $userpurchase['date_p']=$purchase_id->created_at;
             $userpurchase['package']=$package->package;
             PurchaseHistory::where('id','=',$purchase_id->id)->update(['datas'=>json_encode($userpurchase)]);
              $item->purchase_id=$purchase_id->id;
            $item->save();
       }

       dd("done");
   }


   public function ipnnotify(Request $request){
    if(isset($request->recurring_payment_id)){
        $rec_id=$request->recurring_payment_id;
        $paypal=PendingTransactions::where('paypal_agreement_id',$rec_id)->first();
        $package=$paypal->package;
        $pac_amount=Packages::find($package)->amount;
        $user_id=$paypal->user_id;
        $user_pakage=ProfileModel::where('user_id',$user_id)->value('package');
         if($user_pakage == $package && $paypal->payment_status == 'complete' && isset($request->payment_status)){
            
            $cteated_date = date('Y-m-d', strtotime($paypal->created_at));
                if($request->payment_status == 'Completed' && date('Y-m-d') >  $cteated_date){
                    
                    Packages::levelCommission($user_id,$pac_amount,$package);
                }
         }
    }
    else{
        $rec_id='NA';
        $package=0;
        $user_id=0;}

    if(isset($request->payment_cycle))
        $payment_cycle=$request->payment_cycle;
    else
        $payment_cycle='NA';


    if(isset($request->profile_status))
        $profile_status=$request->profile_status;
    else
        $profile_status='NA';

    if(isset($request->payment_date))
        $payment_date=chop($request->payment_date,"PDT");
    else
        $payment_date='NA';

    if(isset($request->next_payment_date))
        $next_payment_date=chop($request->next_payment_date,"PDT");
    else
        $next_payment_date='NA';

    if(isset($request->initial_payment_amount))
        $initial_payment_amount=$request->initial_payment_amount;
    else
        $initial_payment_amount='0.00';

    if(isset($request->amount_per_cycle))
        $amount_per_cycle=$request->amount_per_cycle;
    else
        $amount_per_cycle='0.00';

    if(isset($request->payment_status))
        $payment_status=$request->payment_status;
    else
        $payment_status='Cancelled/No Response';


   $result=IpnResponse::create([
            'payment_id' =>$rec_id,
            'package_id' =>$package,
            'user_id' =>$user_id,
            'payment_cycle'=>$payment_cycle,
            'payment_date'=>$payment_date,
            'next_payment_date'=>$next_payment_date,
            'initial_payment_amount'=>$initial_payment_amount,
            'amount_per_cycle'=>$amount_per_cycle,
            'payment_status'=>$payment_status,
            'profile_status'=>$profile_status,
            'response'=>json_encode($request->all())
          ]);


   dd("done");


   }


    public function store_sponsor($username)
    {
      
        Session::put('replication', $username);
        $sponsor_value = Session::get('replication'); 
        echo  Session::get('replication');        
     
        return Redirect::to('https://algolight.net/');  
       
   }

}
