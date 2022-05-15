rspamd_config.AUTHENTICATED_USER = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}
