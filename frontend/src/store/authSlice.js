import { createSlice } from '@reduxjs/toolkit';

const authSlice = createSlice({
  name: 'auth',
  initialState: {
    /** @type {import('../types/models').User|null} */
    user: null,
    /** @type {'idle'|'loading'|'authed'|'guest'} */
    status: 'idle',
  },
  reducers: {
    setAuthLoading: (state) => {
      state.status = 'loading';
    },
    setUser: (state, action) => {
      state.user = action.payload;
      state.status = 'authed';
    },
    clearAuth: (state) => {
      state.user = null;
      state.status = 'guest';
    },
  },
});

export const { setAuthLoading, setUser, clearAuth } = authSlice.actions;
export default authSlice.reducer;
