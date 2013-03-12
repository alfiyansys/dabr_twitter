<?php
/**********************************************************************************
 *  InMobi Ad Code
 *  Copyright mKhoj Solutions Pvt Ltd and all its subsidiaries. All rights reserved.
 **********************************************************************************/
class MkhojAd 
{
    private $mk_podata; // Request post data
    private $mk_resp;   // Response data
    private $mk_test;   // Mode is test or live
    private $mk_url;
    private $mk_jchar;  // Join char for various multi-value keys

    public function __construct( $mk_siteid )
    {
        $this->mk_podata = array (
            'mk-siteid'    => $mk_siteid,
            'mk-version'   => 'pr-QEQE-CTATE-20090805',
            'mk-ads'       => 1
        );

        $this->mk_resp  = array ();

        $this->mk_test  = false;
        $this->mk_jchar = chr(1);
        $this->mk_url   = 'http://w.mkhoj.com/showad.asm';

        if( array_key_exists('HTTP_REFERER', $_SERVER) )
            $this->mk_podata['h-referer'] = $_SERVER['HTTP_REFERER'];
        if( array_key_exists('HTTP_ACCEPT', $_SERVER) )
            $this->mk_podata['h-accept'] = $_SERVER['HTTP_ACCEPT'];
        if( array_key_exists('HTTP_USER_AGENT', $_SERVER) )
            $this->mk_podata['h-user-agent'] = $_SERVER['HTTP_USER_AGENT'];
        if( array_key_exists('REMOTE_ADDR', $_SERVER) )
            $this->mk_podata['mk-carrier'] = $_SERVER['REMOTE_ADDR'];

        $mk_prot = 'http';
        if( !empty($_SERVER['HTTPS']) && ('on' === $_SERVER['HTTPS']) )
            $mk_prot = 'https';
        $this->mk_podata['h-page-url'] = 
            $mk_prot . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        foreach( $_SERVER as $mk_key => $mk_val )
        {
            if( 0 === strpos($mk_key, 'HTTP_X_') )
            {
                $mk_key = str_replace(array('HTTP_X_', '_'),
                                      array('x-', '-'), $mk_key);
                $this->mk_podata[strtolower($mk_key)] = $mk_val;
            }
        }
    }

    public function fetch_ad( $mk_placement='page' )
    {
        if( !is_scalar($mk_placement) )
            return '';

        
        if( array_key_exists($mk_placement, $this->mk_resp) )
        {
            if(   'page' !== $mk_placement 
               && 0 === count($this->mk_resp[$mk_placement]) 
              ) $mk_placement = 'page';
            return array_shift($this->mk_resp[$mk_placement]);
        }
        else
        {
            if(0 !== count($this->mk_resp['page']))
                return array_shift($this->mk_resp['page']);
        }
            return '';
    }

    public function parse_response( $mk_response )
    {
        if(   null == $mk_response
           || !is_scalar($mk_response) 
           || empty($mk_response) 
          ) return false;

        $mk_respfrags = preg_split("/\cW\cK/", $mk_response);

        foreach( $mk_respfrags as $mk_respfrag )
        {
            $mk_respad = preg_split("/\cT\cX/", $mk_respfrag);

            if( !array_key_exists($mk_respad[0], $this->mk_resp) )
                $this->mk_resp[$mk_respad[0]] = array();

            array_push($this->mk_resp[$mk_respad[0]], $mk_respad[1]);
        }
        return true;
    }

    public function request_ads()
    {
        $mk_timeout = 12;

        $mk_copt = array (
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADER          => false,
            CURLOPT_HTTPPROXYTUNNEL => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $this->get_post_body(),
            CURLOPT_CONNECTTIMEOUT  => $mk_timeout,
            CURLOPT_TIMEOUT         => $mk_timeout,
            CURLOPT_HTTPHEADER      => $this->get_headers()
        );

        if(( $mk_ch = curl_init($this->get_mkhoj_url()) ))
        {
            curl_setopt_array($mk_ch, $mk_copt);

            $mk_retval = curl_exec($mk_ch);
            $mk_httpstatus = curl_getinfo($mk_ch, CURLINFO_HTTP_CODE);
            if( 200 > $mk_httpstatus || 299 < $mk_httpstatus )
            {
                return false;;
            }
            curl_close($mk_ch);

            return $this->parse_response($mk_retval);
        }
        return false;
    }

    public function get_post_body()
    {
        $mk_postbody  = '';
        $mk_innerglue = '=';
        $mk_outerglue = '&';

        $mk_isconsec  = false;

        foreach( $this->mk_podata as $mk_key => $mk_val )
        {
            $mk_pofield = $mk_key . '=' . rawurlencode( $mk_val );
            if( $mk_isconsec )
            {
                $mk_postbody .= '&' . $mk_pofield;
            }
            else
            {
                $mk_isconsec  = true;
                $mk_postbody .= $mk_pofield;
            }
        }
        return $mk_postbody;
    }

    public function get_headers()
    {
        return array(
            'Content-Type: application/x-www-form-urlencoded',
            'X-mKhoj-SiteId: ' . $this->mk_podata['mk-siteid']
        );
    }

    public function get_mkhoj_url()
    {
        return $this->mk_url;
    }

    public function set_user_age( $mk_uage )
    {
        if( is_int($mk_uage) && $mk_uage > 0 )
        {
            $this->mk_podata['u-age'] = $mk_uage;
            return true;
        }
        return false;
    }

    public function set_user_id( $mk_uid )
    {
        if( is_scalar($mk_uid) )
        {
            $this->mk_podata['u-id'] = $mk_uid;
            return true;
        }
        return false;
    }

    public function set_user_gender( $mk_ugender )
    {
        if(   is_scalar($mk_ugender) 
           && (   'f' === strtolower($mk_ugender)
               || 'm' === strtolower($mk_ugender)
               || 't' === strtolower($mk_ugender)
              )
          )
        {
            $this->mk_podata['u-gender'] = strtolower($mk_ugender);
            return true;
        }
        return false;
    }

    public function set_user_location( $mk_ulocation ) 
    {
        if( is_array($mk_ulocation) && 3 >= count($mk_ulocation) ) 
        {
            $this->mk_podata['u-location'] = implode($mk_jchar, $mk_ulocation);
            return true;
        }
        return false;
    }

    public function set_user_interests( $mk_uinterests ) 
    {
        if( is_scalar($mk_uinterests) ) 
        {
            $this->mk_podata['u-interests'] = $mk_uinterests;
            return true;
        }
        return false;
    }

    public function set_page_type( $mk_ptype )
    {
        if( is_scalar($mk_ptype) )
        {
            $this->mk_podata['p-type'] = $mk_ptype;
            return true;
        }
        return false;
    }

    public function set_page_keywords( $mk_pkeyw )
    {
        if( is_scalar($mk_pkeyw) )
        {
            $this->mk_podata['p-keywords'] = $mk_pkeyw;
            return true;
        }
        return false;
    }

    public function set_page_description( $mk_pdesc )
    {
        if( is_scalar($mk_pdesc) )
        {
            $this->mk_podata['p-description'] = $mk_pdesc;
            return true;
        }
        return false;
    }

    public function set_ad_placements( $mk_plcmnt )
    {
        $mk_retval = true;
        if( is_array($mk_plcmnt) )
        {
            $this->mk_podata['mk-placement'] = 
                            implode( $this->mk_jchar, $mk_plcmnt );
            $this->mk_podata['mk-ads'] = count( $mk_plcmnt );
        }
        else if( is_scalar($mk_plcmnt) )
        {
            $this->mk_podata['mk-placement'] = $mk_plcmnt;
            $this->mk_podata['mk-ads'] = 1;
        }
        else
            $mk_retval = false;
        return $mk_retval;
    }

    public function set_num_of_ads( $mk_ads )
    {
        if( is_integer($mk_ads) )
        {
            $this->mk_podata['mk-ads'] = $mk_ads;
            return true;
        }
        return false;
    }

    public function set_banner_size( $mk_banner_size )
    {
        if( is_integer($mk_banner_size) 
            && $mk_banner_size >= 1 && $mk_banner_size <= 4)
        { 
            $this->mk_podata['mk-banner-size'] = $mk_banner_size;
            return true;
        }
        return false;
    }

    public function set_test_mode( $mk_testmode )
    {
        if( is_bool($mk_testmode) )
        {
            $this->mk_test = $mk_testmode;
            if( $mk_testmode ) 
                $this->mk_url  = 'http://w.sandbox.mkhoj.com/showad.asm';
            else
                $this->mk_url  = 'http://w.mkhoj.com/showad.asm';
            return true;
        }
        return false;
    }
}
?>
