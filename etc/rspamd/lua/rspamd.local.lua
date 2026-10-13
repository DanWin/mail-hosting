rspamd_config.AUTHENTICATED_USER = {
	callback = function(task)
		local uname = task:get_user()
		if uname then
			return 1
		end
	end
}

local lua_mime = require "lua_mime"
local lua_util = require "lua_util"
local rspamd_logger = require "rspamd_logger"
local feedback_ids = rspamd_config:add_map{
	type = 'map',
	url = 'https://mail.danwin1210.de/rspamd_feedback_ids.php?token=CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
	description = 'Google Feedback Loop sender IDs',
}

if not feedback_ids then
	error('cannot load Google Feedback Loop sender ID map')
end

rspamd_config:register_symbol{
	name = 'GOOGLE_FEEDBACK_ID',
	type = 'prefilter',
	priority = lua_util.symbols_priorities.top,
	flags = 'empty,nostat',
	callback = function(task)
		local user = task:get_user()
		if not user then
			return
		end

		local feedback_id = feedback_ids:get_key(user:lower())
		if not feedback_id then
			rspamd_logger.errx(task, 'no Google Feedback Loop ID found for authenticated user %s', user)
			return
		end
		if #feedback_id < 5 or #feedback_id > 15 or not feedback_id:match('^[a-f0-9]$') then
			rspamd_logger.errx(task, 'invalid Google Feedback Loop ID for authenticated user %s', user)
			return
		end

		lua_mime.modify_headers(task, {
			remove = { ['Feedback-ID'] = 0 },
			add = {
				['Feedback-ID'] = {
					order = 1,
					value = ':::' .. feedback_id,
				},
			},
			order = { 'Feedback-ID' },
		})
	end,
}
