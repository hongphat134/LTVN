<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\NganhNghe;
use App\BangCap;
use App\CapBac;
use App\KinhNghiem;
use App\NguoiTimViec;
use App\KiNang;
use App\HoSoXinViec;
use App\NhaTuyenDung;
use App\User;
use App\YKien;
use Auth;
use PDF;

class NguoiTimViecController extends Controller
{
    //
    public function getProfiles(){        
        $profile_list = NguoiTimViec::where('idUser','=',Auth::user()->id)->get();
        // dd($profile_list->toArray());       

        return view('nguoitimviec.profile-listings',compact('profile_list'));
    }

    public function getApply($ttd_id){
        $hoso = NguoiTimViec::where('idUser','=',Auth::user()->id)->first();
        // var_dump($hoso);
        // Kiểm tra phòng thờ nếu đã nộp thì chuyển về Home
        $profile = HoSoXinViec::where([
                        ['idUser','=',Auth::user()->id],
                        ['idTTD','=',$ttd_id],
                    ]) 
                    ->first();
        if(!empty($profile)) return redirect('/');

    	return view('nguoitimviec.apply',compact('ttd_id'));
    }

    public function postApply(Request $rq,$ttd_id){
        var_dump($rq->all());
        // Problem: chưa giải quyết withInput 
        $this->validate($rq, 
            [
                //Kiểm tra giá trị rỗng                
                'email' => 'required|email',
                'name' => 'required',
                'title' => 'required',
                'skill' => 'required',
                'exp' => 'required',
                'degree' => 'required',
                'rank' => 'required',
                'region' => 'required',

            ],          
            [
                //Tùy chỉnh hiển thị thông báo
                'email.required' => 'Bạn chưa nhập Email!',
                'name.required' => 'Bạn chưa nhập họ tên!',
                'skill.required' => 'Bạn chưa chọn kĩ năng!',
                'exp.required' => 'Bạn chưa chọn số năm kinh nghiệm!',
                'email.email' => 'Email không đúng định dạng!',
                'title.required' => 'Bạn chưa nhập ngành nghề!',
                'degree.required' => 'Bạn chưa chọn bằng cấp!',
                'rank.required' => 'Bạn chưa chọn cấp bậc!',            
                'region.required' => 'Bạn chưa chọn khu vực!',
            ]
        );      
       
        $profile = new HoSoXinViec;   

        $profile->idUser = Auth::user()->id;            
        $profile->idTTD = $ttd_id;            
                                  
        $skills = json_encode($rq->skill);
        $profile->hoten = $rq->name;
        $profile->kinang = $skills;
        $profile->emaillienhe = $rq->email;
        $profile->nganh = $rq->title;
        $profile->khuvuc = $rq->region;
        $profile->kinhnghiem = $rq->exp;
        $profile->honnhan = $rq->marital_stt;       
        $profile->trangthailv = $rq->status;
        $profile->bangcap = $rq->degree;
        $profile->capbac = $rq->rank;
        $profile->muctieu = $rq->target;    
        $profile->sotruong = $rq->talent;
        $profile->remember_token = $rq->_token;

        if($rq->title != 'other') $profile->nganh = $rq->title;
        else{            
            if(empty($rq->other_title))
             return redirect()->back()->with(['error' => 'Bạn chưa điền ngành nghề khác!'])->withInput();
            else{
                $profile->nganh = perfect_trim($rq->other_title);
                // Bỏ vào table đóng góp ý kiến
                $opinion = new YKien;
                $opinion->ten = $profile->nganh;
                $opinion->loai = "ngành";
                $opinion->save();
            }
        }

        if(!empty($rq->language)){
            $languages = $rq->language;
            if(in_array('other', $languages)){
                // Bỏ mục other
                array_pop($languages);
                if(!empty($rq->other_language)){                
                    $other_languages = explode(',',$rq->other_language);
                    // Chuẩn hoá giá trị của mảng
                    $other_languages = array_map('perfect_trim', $other_languages);
                    
                    $profile->ngoaingu = json_encode(array_merge($languages,$other_languages));
                }
                else $profile->ngoaingu = json_encode($languages);
            }
            else $profile->ngoaingu = json_encode($languages);
        }        
        
        if(!empty($rq->itech)){
            $itechs = $rq->itech;
            if(in_array('other', $itechs)){
                array_pop($itechs);
                if(!empty($rq->other_itech)){                
                    $other_itechs = explode(',',$rq->other_itech);
                    $other_itechs = array_map('perfect_trim', $other_itechs);
                   
                    $profile->tinhoc = json_encode(array_merge($itechs,$other_itechs));
                }
                else $profile->tinhoc = json_encode($itechs);
            }
            else $profile->tinhoc = json_encode($itechs);
        }

        // 0 là chưa phê duyệt, 1 là đã phê duyệt và gửi đến nhà tuyển dụng
        $profile->trangthai = 0;
                
        // dd($profile);
        $profile->save();

        return redirect()->route('notification')->with(['alert' => 'Nộp đơn thành công!']);
    }

    public function getCreateProfile(){    	
    	return view('nguoitimviec.create-profile');
    }

    
    public function postCreateProfile(Request $rq){    	
        var_dump($rq->all());       
        // Problem: chưa giải quyết withInput
    	$this->validate($rq, 
			[
				//Kiểm tra giá trị rỗng
                'public' => 'required',
				'email' => 'required|email',            
                'name' => 'required',
				'title' => 'required',                
                'skill' => 'required',
                'exp' => 'required',
				'degree' => 'required',
				'rank' => 'required',
				'region' => 'required',   
			],			
			[
				//Tùy chỉnh hiển thị thông báo
                'public.required' => 'Bạn chưa chọn chế độ công khai!',
				'email.required' => 'Bạn chưa nhập Email!',
                'name.required' => 'Bạn chưa nhập họ tên!',
                'skill.required' => 'Bạn chưa chọn kĩ năng!',
                'exp.required' => 'Bạn chưa chọn số năm kinh nghiệm!',
				'email.email' => 'Email không đúng định dạng!',
				'title.required' => 'Bạn chưa nhập ngành nghề!',
				'degree.required' => 'Bạn chưa chọn bằng cấp!',
				'rank.required' => 'Bạn chưa chọn cấp bậc!',			
				'region.required' => 'Bạn chưa chọn khu vực!',
			]
		);
    	
		$profile = new NguoiTimViec;	
		$profile->idUser = Auth::user()->id;  	    	
    	  		   
    	// Xử lý file hình đại diện
    	if($rq->hasFile('hinhthe')){
    		$this->validate($rq, 
				[
					//Kiểm tra đúng file đuôi .jpg,.jpeg,.png.gif và dung lượng không quá 2M
					'hinhthe' => 'mimes:jpg,jpeg,png,gif|max:2048',
				],			
				[
					//Tùy chỉnh hiển thị thông báo không thõa điều kiện
					'hinhthe.mimes' => 'Chỉ chấp nhận hình thẻ với đuôi .jpg .jpeg .png .gif',
					'hinhthe.max' => 'Hình thẻ giới hạn dung lượng không quá 2M',
				]
			);

			//Lưu hình ảnh vào thư mục public/upload/hinhthe
			$hinhthe = $rq->file('hinhthe');
			$gethinhthe = time().'_'.$hinhthe->getClientOriginalName();
			$destinationPath = public_path('hinhdaidien');
			$hinhthe->move($destinationPath, $gethinhthe);
			// Xoá hình cũ
    		$file_anh = $profile->hinh;
    		if(!empty($file_anh)) unlink(public_path('hinhdaidien/'.$file_anh));
			$profile->hinh = $gethinhthe;
    	}    	
		// Nếu k có hình thì để hình mặc định
        $skills = json_encode($rq->skill);
        // Chuẩn hoá chuỗi -> chuỗi thường -> đầu từ viết Hoa
        $profile->hoten = perfect_trim($rq->name);
    	$profile->kinang = $skills;
    	$profile->emaillienhe = $rq->email;
    	
    	$profile->khuvuc = $rq->region;
        $profile->kinhnghiem = $rq->exp;
    	$profile->honnhan = $rq->marital_stt;    	
    	$profile->trangthailv = $rq->status;
    	$profile->bangcap = $rq->degree;
    	$profile->capbac = $rq->rank;
        $profile->remember_token = $rq->_token;        
        
        $profile->muctieu = $rq->target;    
        $profile->sotruong = $rq->talent;
        // echo '<pre>'.htmlentities($profile->sotruong).'</pre>';                

        if($rq->title != 'other') $profile->nganh = $rq->title;
        else{            
            if(empty($rq->other_title))
             return redirect()->back()->with(['error' => 'Bạn chưa điền ngành nghề khác!'])->withInput();
            else{
                $profile->nganh = perfect_trim($rq->other_title);
                // Bỏ vào table đóng góp ý kiến
                $opinion = new YKien;
                $opinion->ten = $profile->nganh;
                $opinion->loai = "ngành";
                $opinion->save();
            }
        }

        if(!empty($rq->language)){
            $languages = $rq->language;
            if(in_array('other', $languages)){
                // Bỏ mục other
                array_pop($languages);
                if(!empty($rq->other_language)){                
                    $other_languages = explode(',',$rq->other_language);
                    // Chuẩn hoá giá trị của mảng
                    $other_languages = array_map('perfect_trim', $other_languages);
                    
                    $profile->ngoaingu = json_encode(array_merge($languages,$other_languages));
                }
                else $profile->ngoaingu = json_encode($languages);
            }
            else $profile->ngoaingu = json_encode($languages);
        }        
        
        if(!empty($rq->itech)){
            $itechs = $rq->itech;
            if(in_array('other', $itechs)){
                array_pop($itechs);
                if(!empty($rq->other_itech)){                
                    $other_itechs = explode(',',$rq->other_itech);
                    $other_itechs = array_map('perfect_trim', $other_itechs);
                   
                    $profile->tinhoc = json_encode(array_merge($itechs,$other_itechs));
                }
                else $profile->tinhoc = json_encode($itechs);
            }
            else $profile->tinhoc = json_encode($itechs);
        }                        

    	// 0 là chưa công khai, 1 là công khai
        $profile->congkhai = $rq->public;
        // 0 là chưa dc quản trị viên phê duyệt và 1 thì ngược lại
    	$profile->trangthai = 0;
        
    	// dd($profile);
    	$profile->save();

        return redirect()->action(
            'NguoiTimViecController@getUpdateProfile',['profile_id' => $profile->id] 
        );
    }

    public function getUpdateProfile($profile_id){     
        // Problem: chưa xử lý hình, mục khác chưa hiện dc
        $hoso = NguoiTimViec::find($profile_id);
        // dd($hoso);

        if(!$hoso) return redirect('error')->with(['error' => 'Ko tìm thấy hồ sơ!']);
    
        return view('nguoitimviec.update-profile',compact('hoso'));
    }

    public function postUpdateProfile($profile_id,Request $rq){     
        // var_dump($rq->all());
        // Problem: chưa giải quyết withInput 
        $this->validate($rq, 
            [
                //Kiểm tra giá trị rỗng
                'email' => 'required|email',
                'public' => 'required',
                'name' => 'required',
                'title' => 'required',
                'skill' => 'required',
                'exp' => 'required',
                'degree' => 'required',
                'rank' => 'required',
                'region' => 'required',   
            ],          
            [
                //Tùy chỉnh hiển thị thông báo
                'email.required' => 'Bạn chưa nhập Email!',
                'public.required' => 'Bạn chưa chọn chế độ công khai!',
                'name.required' => 'Bạn chưa nhập họ tên!',
                'skill.required' => 'Bạn chưa chọn kĩ năng!',
                'exp.required' => 'Bạn chưa chọn số năm kinh nghiệm!',
                'email.email' => 'Email không đúng định dạng!',
                'title.required' => 'Bạn chưa nhập ngành nghề!',
                'degree.required' => 'Bạn chưa chọn bằng cấp!',
                'rank.required' => 'Bạn chưa chọn cấp bậc!',            
                'region.required' => 'Bạn chưa chọn khu vực!',
            ]
        );
      
        $profile = NguoiTimViec::find($profile_id);     

        // Xử lý file hình đại diện
        if($rq->hasFile('hinhthe')){
            $this->validate($rq, 
                [
                    //Kiểm tra đúng file đuôi .jpg,.jpeg,.png.gif và dung lượng không quá 2M
                    'hinhthe' => 'mimes:jpg,jpeg,png,gif|max:2048',
                ],          
                [
                    //Tùy chỉnh hiển thị thông báo không thõa điều kiện
                    'hinhthe.mimes' => 'Chỉ chấp nhận hình thẻ với đuôi .jpg .jpeg .png .gif',
                    'hinhthe.max' => 'Hình thẻ giới hạn dung lượng không quá 2M',
                ]
            );

            //Lưu hình ảnh vào thư mục public/upload/hinhthe
            $hinhthe = $rq->file('hinhthe');
            $gethinhthe = time().'_'.$hinhthe->getClientOriginalName();
            $destinationPath = public_path('hinhdaidien');
            $hinhthe->move($destinationPath, $gethinhthe);
            // Xoá hình cũ
            $file_anh = $profile->hinh;
            if(!empty($file_anh)) unlink(public_path('hinhdaidien/'.$file_anh));
            $profile->hinh = $gethinhthe;
        }       
        // Nếu k có hình thì để hình mặc định
        $skills = json_encode($rq->skill);
        $profile->hoten = $rq->name;
        $profile->kinang = $skills;
        $profile->emaillienhe = $rq->email;
        $profile->nganh = $rq->title;
        $profile->khuvuc = $rq->region;
        $profile->kinhnghiem = $rq->exp;
        $profile->honnhan = $rq->marital_stt;       
        $profile->trangthailv = $rq->status;
        $profile->bangcap = $rq->degree;
        $profile->capbac = $rq->rank;
        $profile->remember_token = $rq->_token;

        $profile->muctieu = $rq->target;    
        $profile->sotruong = $rq->talent;

        if($rq->title != 'other') $profile->nganh = $rq->title;
        else{            
            if(empty($rq->other_title))
             return redirect()->back()->with(['error' => 'Bạn chưa điền ngành nghề khác!'])->withInput();
            else{
                $profile->nganh = perfect_trim($rq->other_title);
                // Bỏ vào table đóng góp ý kiến
                $opinion = new YKien;
                $opinion->ten = $profile->nganh;
                $opinion->loai = "ngành";
                $opinion->save();
            }
        }

        if(!empty($rq->language)){
            $languages = $rq->language;
            if(in_array('other', $languages)){
                // Bỏ mục other
                array_pop($languages);
                if(!empty($rq->other_language)){                
                    $other_languages = explode(',',$rq->other_language);
                    // Chuẩn hoá giá trị của mảng
                    $other_languages = array_map('perfect_trim', $other_languages);
                    
                    $profile->ngoaingu = json_encode(array_merge($languages,$other_languages));
                }
                else $profile->ngoaingu = json_encode($languages);
            }
            else $profile->ngoaingu = json_encode($languages);
        }        
        
        if(!empty($rq->itech)){
            $itechs = $rq->itech;
            if(in_array('other', $itechs)){
                array_pop($itechs);
                if(!empty($rq->other_itech)){                
                    $other_itechs = explode(',',$rq->other_itech);
                    $other_itechs = array_map('perfect_trim', $other_itechs);
                   
                    $profile->tinhoc = json_encode(array_merge($itechs,$other_itechs));
                }
                else $profile->tinhoc = json_encode($itechs);
            }
            else $profile->tinhoc = json_encode($itechs);
        }              

        // 0 là chưa công khai, 1 là công khai
        $profile->congkhai = $rq->public;
                
        // dd($profile);
        $profile->update();

        return redirect()->action(
            'NguoiTimViecController@getUpdateProfile', $profile_id
        )
        ->with(['success' => 'Lưu thành công!']);
    }

    public function saveJob($news_id){        
        $follow = json_decode(Auth::user()->theodoi);

        // Nếu chưa có follow nào cả thì tạo mảng mới
        if(!is_array($follow)) $follow = array();
        // Chèn vào đầu mảng
        // Phòng trường hợp nếu có r thì k thêm nữa
        if(!in_array($news_id,$follow)) array_unshift($follow, $news_id);

        Auth::user()->theodoi = json_encode($follow);

        Auth::user()->update();
        echo "Đã thêm ttd";
    }

    public function unsaveJob($news_id){        
        $follow = json_decode(Auth::user()->theodoi);

        // Xoá phần tử trong mảng
        // Xoá dc mà auto index lại hiện lên
        // unset($follow[0]);
        $index = array_search($news_id, $follow);
        array_splice($follow, $index, 1);

        Auth::user()->theodoi = json_encode($follow);

        Auth::user()->update();
        echo "Đã bỏ theo dõi ttd";
    }

    public function getSaveJob(){
        // Problem: chưa lấy info theo thứ tự được 
        // Tin tuyển dụng bị xoá r thì s?
        $follow_list = json_decode(Auth::user()->theodoi);

        // dd($follow_list);
        if(!empty($follow_list)){
            $job_listings = NhaTuyenDung::join('tintuyendung','nhatuyendung.idUser','=','tintuyendung.idNTD')
            ->whereIn('tintuyendung.id',$follow_list)->paginate(3);
        
            // Chuyển JSON kĩ năng thành mảng
            for ($i=0; $i < count($job_listings) ; $i++) { 
                $job_listings[$i]->kinang =  json_decode($job_listings[$i]->kinang);
                $skills = array();
                for ($j=0; $j < count($job_listings[$i]->kinang) ; $j++) {                 
                    $skills[] = KiNang::find($job_listings[$i]->kinang[$j])->ten;            
                }
                $job_listings[$i]->kinang = $skills;
            }                
            // dd($job_listings);
        }
        else $job_listings = null;
            
        return view('nguoitimviec.save-job-listings',compact('job_listings'));
    }

    public function getAppliedJob(){
        // Problem: chưa lấy info theo thứ tự được               
        $profiles = HoSoXinViec::where('idUser','=',Auth::user()->id)
                    ->select('idTTD')->get()->toArray();

        $job_listings = NhaTuyenDung::join('tintuyendung','nhatuyendung.idUser','=','tintuyendung.idNTD')
        ->whereIn('tintuyendung.id',$profiles)->paginate(3);
    
        // Chuyển JSON kĩ năng thành mảng
        for ($i=0; $i < count($job_listings) ; $i++) { 
            $job_listings[$i]->kinang =  json_decode($job_listings[$i]->kinang);
            $skills = array();
            for ($j=0; $j < count($job_listings[$i]->kinang) ; $j++) {                 
                $skills[] = KiNang::find($job_listings[$i]->kinang[$j])->ten;            
            }
            $job_listings[$i]->kinang = $skills;
        }                
        // dd($job_listings);
            
        return view('nguoitimviec.applied-job-listings',compact('job_listings'));
    }

    public function deleteProfile($profile_id){
        // $profile = NguoiTimViec::find($profile_id);
        // dd($profile);
        NguoiTimViec::destroy($profile_id);

        return redirect()->back()->with(['success' => 'Đã xoá mẫu hồ sơ!']);
    }

    public function getSelectApply($news_id){
        // Kiểm tra phòng thờ nếu đã nộp thì chuyển về Home
        $profile = HoSoXinViec::where([
                        ['idUser','=',Auth::user()->id],
                        ['idTTD','=',$news_id],
                    ])    
                    ->first();
        if(!empty($profile)) return redirect('/');
        $profiles = NguoiTimViec::where('idUser','=',Auth::user()->id)->get();        
        return view('nguoitimviec.select-apply',compact('profiles','news_id'));
    }

    public function apply(Request $rq){
        // var_dump($rq->all());
        if(empty($rq->profile)) return redirect()->back()->with(['error' => 'Bạn chưa chọn hồ sơ!']);

        $profile_id = $rq->profile;
        
        $profile = NguoiTimViec::find($profile_id);
        // dd($profile);

        $apply_profile = new HoSoXinViec;

        $apply_profile->idUser = $profile->idUser;
        $apply_profile->idTTD = (int)$rq->ttd_id;

        $apply_profile->emaillienhe = $profile->emaillienhe;
        $apply_profile->nganh = $profile->nganh;
        $apply_profile->kinang = $profile->kinang;
        $apply_profile->khuvuc = $profile->khuvuc;
        $apply_profile->honnhan = $profile->honnhan;
        $apply_profile->trangthailv = $profile->trangthailv;
        $apply_profile->bangcap = $profile->bangcap;
        $apply_profile->capbac = $profile->capbac;
        $apply_profile->kinhnghiem = $profile->kinhnghiem;        
        $apply_profile->trangthai = $profile->trangthai;
        $apply_profile->remember_token = $profile->remember_token;     

        $apply_profile->save();

        return redirect()->route('notification')->with(['alert' => 'Nộp đơn thành công!']);
    }

    public function setStatus($profile_id){
        $profile = NguoiTimViec::find($profile_id);

        $profile->congkhai = ($profile->congkhai == 0) ? 1 : 0;

        $profile->update();

        return redirect()->back()->with(['success' => 'Đổi trạng thái thành công!']);
    }

    public function pdfProfile($profile_id){
        $profile = NguoiTimViec::find($profile_id); 
        // dd($profile);       
        $pdf = PDF::loadView('nguoitimviec.pdf-profile',compact('profile'));
        return $pdf->stream();
        // download với tên file list_book với folder chỉ định
        //return $pdf->download('list_book.pdf');

        //Tự động download file với folder trong hàm save() và chuyển trang pdf với tên là download
        // return PDF::loadView('admin.book.pdf',compact('data'))->save('C:/Users/Phat/Downloads/Compressed/myfile.pdf')->stream('download.pdf');

        //PDF::loadView('admin.book.pdf',compact('data'))->setPaper('a2', 'landscape')->setWarnings(false)->save('C:/Users/Phat/Downloads/Compressed/myfile.pdf');
    }
}