(function ($) {     
	jQuery('#ds-commission').dataTable({    
	    "bSort": true,
	    "bPaginate": true,
	    "bLengthChange": true,
	    "bFilter": true,
	    "bInfo": true,
	    "bAutoWidth": true, 
	    "sDom": 'T<"panel-menu dt-panelmenu"lfr><"clearfix">tip',
	    "oTableTools": {
    	  "sSwfPath": "<a href='//cdnjs.cloudflare.com/ajax/libs/datatables-tabletools/2.1.5/swf/copy_csv_xls_pdf.swf' target='_blank' rel='nofollow'>http://cdnjs.cloudflare.com/ajax/libs/datatables-tabletools/2.1.5/swf/copy_csv_xls_pdf.swf</a>",
	      // "sSwfPath": "http://cdnjs.cloudflare.com/ajax/libs/datatables-tabletools/2.1.5/swf/copy_csv_xls_pdf.swf",
	      "aButtons": [ 
	          "csv",
	          "xls",
	          {
	              "sExtends": "pdf",
	              "bFooter": true,
	              "sPdfMessage": "List of All Orders ",
	              "sPdfOrientation": "landscape"
	          },
	          "print"
	    ]}
	});   
}(jQuery)); //end document.ready
 