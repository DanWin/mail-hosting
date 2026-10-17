local custom_keywords = {}
local ratelimit_users = rspamd_config:add_map({
    type = 'map',
    url = 'https://mail.danwin1210.de/rspamd_ratelimits.php?token=CHANGE_ME_TO_A_LONG_RANDOM_SECRET',
    description = 'Ratelimits for authenticated users',
})

if not ratelimit_users then
    error('cannot load authenticated user ratelimit map')
end

local default_limits = {
    per_minute = { burst = 20, rate = '2 / 1m' },
    per_three_hours = { burst = 400, rate = '50 / 3h' },
}
local new_user_limits = {
    per_minute = { burst = 10, rate = '1 / 1m' },
    per_three_hours = { burst = 100, rate = '20 / 3h' },
}
local trusted_user_limits = {
    per_minute = { burst = 50, rate = '10 / 1m' },
    per_three_hours = { burst = 1000, rate = '100 / 3h' },
}

local function get_user_limit(task, limit_period)
    local user = task:get_user()
    if not user then
        return nil
    end

    local normalized_user = user:lower()
    local classification = ratelimit_users:get_key(normalized_user)
    local limits = default_limits
    if classification == 'NEW' then
        limits = new_user_limits
    elseif classification == 'TRUSTED' then
        limits = trusted_user_limits
    end

    return normalized_user .. '_sending_limits_' .. limit_period, limits[limit_period]
end

custom_keywords.sending_limits_per_minute = function(task)
    return get_user_limit(task, 'per_minute')
end

custom_keywords.sending_limits_per_three_hours = function(task)
    return get_user_limit(task, 'per_three_hours')
end

return custom_keywords
