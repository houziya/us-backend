require('NSString,HttpManager,NSUserDefaults,AuthData,UIScreen');
defineClass('NSString', {
    containsString: function(str) {
        if (str && self.length() && str.length()) {
            var range = self.rangeOfString(str);
            if (!range.length) {
                return NO;
            }
            return YES;
        }
        return NO;
    },
});
defineClass('USSwitchViewController', {
    viewDidAppear: function(animated) {
        self.super().viewDidAppear(animated);

        var weak_self = __weak(self);
            
        var apidomain = NSUserDefaults.standardUserDefaults().objectForKey("apiDomain");
        if (!apidomain.hasPrefix("http")) {
            apidomain = NSString.alloc().initWithString("http://").stringByAppendingString(apidomain);
        }
        var url = apidomain.stringByAppendingString("/Us/User/userConfig");
        HttpManager.defaultManager().getCacheToUrl_params_complete(url, null, block('BOOL,HttpResponse*', function(successed, response) {
            if(successed && !(response.valueForKey("is_cache"))) {
                var loginUser = AuthData.loginUser();
                loginUser.populateWithObject(response.payload());
                loginUser.synchronize();
                                                                                    
                var sself = __strong(weak_self);
                if(sself){
                    sself.valueForKey("_tableView").reloadData();
                }
            }
        }));
    },
});
defineClass('USHomeViewController', {
    viewDidLayoutSubviews: function() {
        self.super().viewDidLayoutSubviews();
            
        if (self.view().frame().width == UIScreen.mainScreen().bounds().width) {
            var height = self.view().frame().height;
            
            var navArray = self.navArray().toJS();
            for (var i = 0; i < navArray.length; i ++) {
               navArray[i].view().autoSetDimension_toSize(8, height);
            }
        }
    },
});